#!/usr/bin/env python3
"""
Comprehensive Polaris Notification Sample Data Generator
Generates interconnected, realistic sample data with proper cross-references
"""

import csv
import random
import string
from datetime import datetime, timedelta
from pathlib import Path
from faker import Faker
from dataclasses import dataclass, field
from typing import List, Dict, Optional

# Initialize Faker with seed for reproducibility
fake = Faker()
Faker.seed(42)
random.seed(42)

# Constants
SAMPLE_DATA_DIR = Path("polaris-databases/sample-data")
TODAY = datetime.now()
PAST_DAYS = 20
FUTURE_DAYS = 10

# Delivery option distribution
DELIVERY_OPTIONS = {
    2: 0.60,  # Email - 60%
    8: 0.20,  # SMS - 20%
    3: 0.10,  # Voice - 10%
    1: 0.10,  # Mail - 10%
}

# Notification statuses
STATUS_EMAIL_SUCCESS = 12
STATUS_EMAIL_FAILED = 14
STATUS_CALL_VOICE = 1
STATUS_CALL_MACHINE = 2
STATUS_SENT = 16
STATUS_MAIL_PRINTED = 15

# Notification types
NOTIF_TYPE_OVERDUE_1ST = 1
NOTIF_TYPE_HOLD = 2
NOTIF_TYPE_ALMOST_OVERDUE = 7  # Almost overdue/Auto-renew reminder/Courtesy
NOTIF_TYPE_OVERDUE_REMINDER = 7
NOTIF_TYPE_FINE = 8

# Circulation constants
CHECKOUT_PERIOD = 21  # Days
MAX_RENEWALS = 2


def apply_sunday_rule(date: datetime) -> datetime:
    """
    Apply the Sunday rule: if date falls on Sunday, move to Monday.
    This applies to due dates and hold expiration dates.
    """
    if date.weekday() == 6:  # Sunday = 6
        return date + timedelta(days=1)
    return date


@dataclass
class Patron:
    """Patron data structure"""
    patron_id: int
    barcode: str
    first_name: str
    last_name: str
    middle_name: str
    email: str
    phone: str
    delivery_option_id: int
    password_hash: str
    obfuscated_password: str
    full_name: str
    first_last_name: str

    # Holds, overdues, and almost overdues for this patron
    holds: List['Hold'] = field(default_factory=list)
    overdues: List['Overdue'] = field(default_factory=list)
    almost_overdues: List['AlmostOverdue'] = field(default_factory=list)


@dataclass
class Item:
    """Item/Book data structure"""
    item_record_id: int
    barcode: str
    title: str
    author: str
    call_number: str
    price: float
    format_id: int = 49  # Book format


@dataclass
class Hold:
    """Hold request data structure"""
    sys_hold_request_id: int
    patron_id: int
    item_record_id: int
    creation_date: datetime
    hold_notification_date: datetime
    hold_till_date: datetime
    delivery_option_id: int
    pickup_org_id: int = 3  # DCPL main


@dataclass
class Overdue:
    """Overdue item data structure"""
    patron_id: int
    item_record_id: int
    due_date: datetime
    checkout_date: datetime
    renewals: int  # 0, 1, or 2
    overdue_notice_count: int  # 0, 1, or 2
    notification_type_id: int = NOTIF_TYPE_OVERDUE_1ST


@dataclass
class AlmostOverdue:
    """Almost overdue item (due in 3 days)"""
    patron_id: int
    item_record_id: int
    due_date: datetime
    checkout_date: datetime
    renewals: int  # 0, 1, or 2 (exhausted renewals)
    renewal_limit: int = 2


@dataclass
class Checkout:
    """Currently checked out item"""
    patron_id: int
    item_record_id: int
    checkout_date: datetime
    due_date: datetime
    renewals: int
    renewal_limit: int = 2


class DataGenerator:
    """Main data generation class"""

    def __init__(self):
        self.patrons: List[Patron] = []
        self.items: List[Item] = []
        self.holds: List[Hold] = []
        self.overdues: List[Overdue] = []
        self.almost_overdues: List[AlmostOverdue] = []
        self.notification_batches: Dict[datetime, List] = {}

        # Counters
        self.patron_id_start = 10000
        self.item_id_start = 100000
        self.hold_id_start = 800000

    def generate_barcode(self, prefix: str, length: int = 14) -> str:
        """Generate a barcode with prefix"""
        remaining = length - len(prefix)
        return prefix + ''.join([str(random.randint(0, 9)) for _ in range(remaining)])

    def generate_password_hash(self) -> str:
        """Generate realistic bcrypt hash"""
        chars = string.ascii_letters + string.digits + './'
        return f"$2a$10${''.join(random.choices(chars, k=53))}"

    def generate_obfuscated_password(self) -> str:
        """Generate obfuscated password"""
        chars = string.ascii_letters + string.digits + '+/'
        return ''.join(random.choices(chars, k=22)) + "=="

    def generate_phone(self) -> str:
        """Generate fake phone number with 270 area code and 555 exchange"""
        # Use 555-0100 through 555-0199 (reserved for fictional use)
        last_four = random.randint(100, 199)
        return f"2705550{last_four}"

    def generate_email(self, first_name: str, last_name: str) -> str:
        """Generate realistic email"""
        providers = ["gmail.com", "yahoo.com", "hotmail.com", "outlook.com", "icloud.com"]
        formats = [
            f"{first_name.lower()}.{last_name.lower()}",
            f"{first_name.lower()}{last_name.lower()}",
            f"{first_name.lower()}{random.randint(1, 999)}",
            f"{last_name.lower()}{first_name[0].lower()}",
        ]
        return f"{random.choice(formats)}@{random.choice(providers)}"

    def get_delivery_option(self) -> int:
        """Get weighted random delivery option"""
        options = list(DELIVERY_OPTIONS.keys())
        weights = list(DELIVERY_OPTIONS.values())
        return random.choices(options, weights=weights)[0]

    def generate_batch_time(self, base_date: datetime, hour: int, minute: int = 0) -> datetime:
        """Generate notification batch time with realistic variance"""
        batch_time = base_date.replace(hour=hour, minute=minute, second=0, microsecond=0)
        # Add small variance (0-59 seconds)
        variance = timedelta(seconds=random.randint(0, 59))
        return batch_time + variance

    def get_hold_batch_time(self, base_date: datetime) -> datetime:
        """Get random hold notification batch time (8:05, 9:05, 13:05, or 17:05)"""
        batch_hours = [8, 9, 13, 17]
        hour = random.choice(batch_hours)
        return self.generate_batch_time(base_date, hour, minute=5)

    def calculate_checkout_date(self, due_date: datetime, renewals: int) -> datetime:
        """Calculate checkout date based on due date and number of renewals"""
        # Work backward from due date
        # Total days = CHECKOUT_PERIOD + (renewals * CHECKOUT_PERIOD)
        total_days = CHECKOUT_PERIOD * (renewals + 1)
        return due_date - timedelta(days=total_days)

    def generate_patrons(self):
        """Generate patron scenarios based on real patterns"""
        print("Generating patrons...")

        scenarios = [
            # High-volume users
            {"holds": 0, "overdues": 7, "desc": "Music enthusiast (7 overdues)"},
            {"holds": 0, "overdues": 5, "desc": "Heavy borrower (5 overdues)"},
            {"holds": 0, "overdues": 4, "desc": "Series collector (4 overdues)"},

            # Multi-hold users
            {"holds": 3, "overdues": 0, "desc": "Topic collector (3 holds)"},
            {"holds": 2, "overdues": 0, "desc": "Movie fan (2 holds)"},
            {"holds": 2, "overdues": 0, "desc": "Fiction reader (2 holds)"},

            # Mixed activity
            {"holds": 2, "overdues": 1, "desc": "Mixed patron (2 holds, 1 overdue)"},
            {"holds": 1, "overdues": 2, "desc": "Mixed patron (1 hold, 2 overdues)"},
            {"holds": 1, "overdues": 3, "desc": "Mixed patron (1 hold, 3 overdues)"},
            {"holds": 3, "overdues": 1, "desc": "Active reader (3 holds, 1 overdue)"},

            # Single item users (10 patrons)
            *[{"holds": 1, "overdues": 0, "desc": f"Single hold patron {i}"} for i in range(5)],
            *[{"holds": 0, "overdues": 1, "desc": f"Single overdue patron {i}"} for i in range(5)],

            # No current activity (5 patrons)
            *[{"holds": 0, "overdues": 0, "desc": f"Inactive patron {i}"} for i in range(5)],
        ]

        for idx, scenario in enumerate(scenarios):
            patron_id = self.patron_id_start + idx
            gender = random.choice([0, 1])

            if gender == 0:
                first_name = fake.first_name_female()
            else:
                first_name = fake.first_name_male()

            last_name = fake.last_name()
            middle_name = fake.first_name() if random.random() > 0.3 else ""

            delivery_option = self.get_delivery_option()

            # Generate contact info for most patrons (85% have email, 85% have phone)
            # Contact info is independent of delivery preference
            email = self.generate_email(first_name, last_name) if random.random() > 0.15 else ""
            phone = self.generate_phone() if random.random() > 0.15 else ""

            # Validate required contact for chosen delivery method
            if delivery_option == 2 and not email:
                # MUST have email for email delivery
                email = self.generate_email(first_name, last_name)

            if delivery_option in [3, 8] and not phone:
                # MUST have phone for voice/SMS delivery
                phone = self.generate_phone()

            full_name = f"{last_name.upper()}, {first_name.upper()}"
            if middle_name:
                full_name += f" {middle_name.upper()}"

            first_last = f"{first_name.upper()}"
            if middle_name:
                first_last += f" {middle_name.upper()}"
            first_last += f" {last_name.upper()}"

            patron = Patron(
                patron_id=patron_id,
                barcode=self.generate_barcode("23307", 14),
                first_name=first_name.upper(),
                last_name=last_name.upper(),
                middle_name=middle_name.upper(),
                email=email,
                phone=phone,
                delivery_option_id=delivery_option,
                password_hash=self.generate_password_hash(),
                obfuscated_password=self.generate_obfuscated_password(),
                full_name=full_name,
                first_last_name=first_last,
            )

            self.patrons.append(patron)

            # Track for later generation
            patron.holds = []
            patron.overdues = []

        print(f"  Generated {len(self.patrons)} patrons")

    def generate_items(self):
        """Generate items (books, DVDs, etc.)"""
        print("Generating items...")

        # Calculate total items needed
        total_holds = sum(len(p.holds) for p in self.patrons)
        total_overdues = sum(len(p.overdues) for p in self.patrons)

        # We'll need items for all holds and overdues, plus some extras
        needed_items = 100  # Base set of items

        item_types = [
            ("fiction", "F", 0.30),
            ("juvenile_fiction", "JF", 0.20),
            ("nonfiction", "dewey", 0.20),
            ("dvd", "DVD", 0.15),
            ("game", "GAME", 0.10),
            ("young_adult", "YA", 0.05),
        ]

        for i in range(needed_items):
            item_id = self.item_id_start + i

            # Choose item type
            item_type = random.choices(
                [t[0] for t in item_types],
                weights=[t[2] for t in item_types]
            )[0]

            if item_type == "fiction":
                author = fake.name()
                title = fake.catch_phrase()
                call_number = f"F {author.split()[-1][:4]}"
                price = round(random.uniform(12.99, 29.99), 2)

            elif item_type == "juvenile_fiction":
                author = fake.name()
                title = f"{fake.word().title()} and the {fake.word()}"
                call_number = f"JF {author.split()[-1][:4]}"
                price = round(random.uniform(8.99, 19.99), 2)

            elif item_type == "nonfiction":
                author = fake.name()
                dewey = f"{random.randint(0, 999):03d}.{random.randint(0, 99):02d}"
                title = fake.bs().title()
                call_number = f"{dewey} {author.split()[-1][:4]}"
                price = round(random.uniform(15.99, 45.99), 2)

            elif item_type == "dvd":
                title = f"{fake.catch_phrase()} [{fake.year()}]"
                author = None
                genres = ["ACTION", "COMEDY", "DRAMA", "HORROR", "SCI-FI"]
                call_number = f"DVD {random.choice(genres)} {fake.word()[:4].upper()}"
                price = round(random.uniform(9.99, 24.99), 2)

            elif item_type == "game":
                platforms = ["PS5", "XBOX", "SWITCH", "PS4"]
                title = f"{fake.catch_phrase()} [{random.choice(platforms)}]"
                author = None
                call_number = f"{random.choice(platforms)} {fake.word()[:4].upper()}"
                price = round(random.uniform(29.99, 69.99), 2)

            else:  # young_adult
                author = fake.name()
                title = f"The {fake.word().title()} of {fake.word().title()}"
                call_number = f"YA {author.split()[-1][:4]}"
                price = round(random.uniform(10.99, 22.99), 2)

            item = Item(
                item_record_id=item_id,
                barcode=self.generate_barcode("33307", 14),
                title=title,
                author=author if author else "",
                call_number=call_number,
                price=price,
            )

            self.items.append(item)

        print(f"  Generated {len(self.items)} items")

    def generate_holds_and_overdues(self):
        """Generate holds, overdues, and almost overdues for patrons"""
        print("Generating holds, overdues, and almost overdues...")

        scenarios = [
            # High-volume users
            {"holds": 0, "overdues": 7, "almost_overdues": 0},
            {"holds": 0, "overdues": 5, "almost_overdues": 0},
            {"holds": 0, "overdues": 4, "almost_overdues": 1},
            # Multi-hold users
            {"holds": 3, "overdues": 0, "almost_overdues": 0},
            {"holds": 2, "overdues": 0, "almost_overdues": 1},
            {"holds": 2, "overdues": 0, "almost_overdues": 0},
            # Mixed activity
            {"holds": 2, "overdues": 1, "almost_overdues": 0},
            {"holds": 1, "overdues": 2, "almost_overdues": 1},
            {"holds": 1, "overdues": 3, "almost_overdues": 0},
            {"holds": 3, "overdues": 1, "almost_overdues": 1},
            # Single item users (10 patrons)
            *[{"holds": 1, "overdues": 0, "almost_overdues": 0} for _ in range(4)],
            {"holds": 0, "overdues": 0, "almost_overdues": 1},  # Almost overdue only
            *[{"holds": 0, "overdues": 1, "almost_overdues": 0} for _ in range(4)],
            {"holds": 0, "overdues": 0, "almost_overdues": 2},  # 2 almost overdues
            # No current activity (5 patrons)
            *[{"holds": 0, "overdues": 0, "almost_overdues": 0} for _ in range(5)],
        ]

        item_idx = 0

        for patron, scenario in zip(self.patrons, scenarios):
            # Generate holds
            for _ in range(scenario["holds"]):
                if item_idx >= len(self.items):
                    break

                item = self.items[item_idx]
                item_idx += 1

                # Hold created 1-10 days ago
                creation_date = TODAY - timedelta(days=random.randint(1, 10))
                # Notified same day or next day
                notification_date = creation_date + timedelta(hours=random.randint(1, 24))
                # Expires 3-5 days from notification (apply Sunday rule)
                raw_expiration = notification_date + timedelta(days=random.randint(3, 5))
                hold_till_date = apply_sunday_rule(raw_expiration)

                hold = Hold(
                    sys_hold_request_id=self.hold_id_start + len(self.holds),
                    patron_id=patron.patron_id,
                    item_record_id=item.item_record_id,
                    creation_date=creation_date,
                    hold_notification_date=notification_date,
                    hold_till_date=hold_till_date,
                    delivery_option_id=patron.delivery_option_id,
                )

                self.holds.append(hold)
                patron.holds.append(hold)

            # Generate overdues
            for idx in range(scenario["overdues"]):
                if item_idx >= len(self.items):
                    break

                item = self.items[item_idx]
                item_idx += 1

                # Due date in the past (1-20 days ago), apply Sunday rule
                days_overdue = random.randint(1, PAST_DAYS)
                raw_due_date = TODAY - timedelta(days=days_overdue)
                due_date = apply_sunday_rule(raw_due_date)

                # Random renewal count (0, 1, or 2)
                renewals = random.choice([0, 0, 1, 1, 2])  # Weighted toward fewer renewals

                # Calculate checkout date
                checkout_date = self.calculate_checkout_date(due_date, renewals)

                # Escalation based on days overdue
                if days_overdue <= 7:
                    notice_count = 0  # First notice
                elif days_overdue <= 14:
                    notice_count = 1  # Second notice
                else:
                    notice_count = 2  # Billing notice

                overdue = Overdue(
                    patron_id=patron.patron_id,
                    item_record_id=item.item_record_id,
                    due_date=due_date,
                    checkout_date=checkout_date,
                    renewals=renewals,
                    overdue_notice_count=notice_count,
                )

                self.overdues.append(overdue)
                patron.overdues.append(overdue)

            # Generate almost overdues (items due in 3 days)
            for idx in range(scenario["almost_overdues"]):
                if item_idx >= len(self.items):
                    break

                item = self.items[item_idx]
                item_idx += 1

                # Due in exactly 3 days (for courtesy notifications), apply Sunday rule
                raw_due_date = TODAY + timedelta(days=3)
                due_date = apply_sunday_rule(raw_due_date)

                # Almost overdues should have exhausted renewals (2 renewals)
                # This is realistic - they've been auto-renewed twice already
                renewals = MAX_RENEWALS

                # Calculate checkout date
                checkout_date = self.calculate_checkout_date(due_date, renewals)

                almost_overdue = AlmostOverdue(
                    patron_id=patron.patron_id,
                    item_record_id=item.item_record_id,
                    due_date=due_date,
                    checkout_date=checkout_date,
                    renewals=renewals,
                )

                self.almost_overdues.append(almost_overdue)
                patron.almost_overdues.append(almost_overdue)

        print(f"  Generated {len(self.holds)} holds")
        print(f"  Generated {len(self.overdues)} overdues")
        print(f"  Generated {len(self.almost_overdues)} almost overdues")

    def generate_notification_history(self):
        """Generate notification history with proper time correlations"""
        print("Generating notification history...")

        history = []
        logs = []

        # Process holds
        for hold in self.holds:
            patron = next(p for p in self.patrons if p.patron_id == hold.patron_id)
            item = next(i for i in self.items if i.item_record_id == hold.item_record_id)

            # Determine notification time based on delivery method
            # Email/Mail: 8:00 AM
            # SMS/Voice: 8:05, 9:05, 13:05, or 17:05 (random batch)
            if hold.delivery_option_id in [1, 2]:  # Email or Mail
                notice_date = self.generate_batch_time(hold.hold_notification_date, hour=8, minute=0)
            else:  # SMS (8) or Voice (3)
                notice_date = self.get_hold_batch_time(hold.hold_notification_date)

            # Determine status based on delivery method
            if hold.delivery_option_id == 2:  # Email
                status_id = STATUS_EMAIL_SUCCESS if random.random() > 0.05 else STATUS_EMAIL_FAILED
                delivery_string = patron.email
            elif hold.delivery_option_id == 8:  # SMS
                status_id = STATUS_SENT
                delivery_string = patron.phone
            elif hold.delivery_option_id == 3:  # Voice
                status_id = random.choices([STATUS_CALL_VOICE, STATUS_CALL_MACHINE], weights=[0.7, 0.3])[0]
                delivery_string = patron.phone
            else:  # Mail
                status_id = STATUS_MAIL_PRINTED
                delivery_string = ""

            history.append({
                "PatronId": patron.patron_id,
                "ItemRecordId": item.item_record_id,
                "TxnId": None,
                "NotificationTypeId": NOTIF_TYPE_HOLD,
                "ReportingOrgId": 3,
                "DeliveryOptionId": hold.delivery_option_id,
                "NoticeDate": notice_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-5],
                "Amount": 0,
                "NotificationStatusId": status_id,
                "Title": item.title,
            })

            # Add to batch tracking for logs
            batch_key = notice_date.replace(second=0, microsecond=0)
            if batch_key not in self.notification_batches:
                self.notification_batches[batch_key] = []
            self.notification_batches[batch_key].append({
                "patron_id": patron.patron_id,
                "type": "hold",
                "delivery_option": hold.delivery_option_id,
                "delivery_string": delivery_string,
                "status": status_id,
            })

        # Process overdues
        for overdue in self.overdues:
            patron = next(p for p in self.patrons if p.patron_id == overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == overdue.item_record_id)

            # Determine notification time based on delivery method
            # Email/Mail: 8:00 AM day after due date
            # SMS/Voice: 8:04 AM day after due date
            notification_date = overdue.due_date + timedelta(days=1)
            if patron.delivery_option_id in [1, 2]:  # Email or Mail
                notice_date = self.generate_batch_time(notification_date, hour=8, minute=0)
            else:  # SMS (8) or Voice (3)
                notice_date = self.generate_batch_time(notification_date, hour=8, minute=4)

            # Determine status
            if patron.delivery_option_id == 2:
                status_id = STATUS_EMAIL_SUCCESS if random.random() > 0.05 else STATUS_EMAIL_FAILED
                delivery_string = patron.email
            elif patron.delivery_option_id == 8:
                status_id = STATUS_SENT
                delivery_string = patron.phone
            elif patron.delivery_option_id == 3:
                status_id = random.choices([STATUS_CALL_VOICE, STATUS_CALL_MACHINE], weights=[0.7, 0.3])[0]
                delivery_string = patron.phone
            else:
                status_id = STATUS_MAIL_PRINTED
                delivery_string = ""

            history.append({
                "PatronId": patron.patron_id,
                "ItemRecordId": item.item_record_id,
                "TxnId": None,
                "NotificationTypeId": NOTIF_TYPE_OVERDUE_1ST,
                "ReportingOrgId": 3,
                "DeliveryOptionId": patron.delivery_option_id,
                "NoticeDate": notice_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-5],
                "Amount": 0,
                "NotificationStatusId": status_id,
                "Title": item.title,
            })

            batch_key = notice_date.replace(second=0, microsecond=0)
            if batch_key not in self.notification_batches:
                self.notification_batches[batch_key] = []
            self.notification_batches[batch_key].append({
                "patron_id": patron.patron_id,
                "type": "overdue",
                "delivery_option": patron.delivery_option_id,
                "delivery_string": delivery_string,
                "status": status_id,
            })

        # Process almost overdues (courtesy reminders)
        for almost_overdue in self.almost_overdues:
            patron = next(p for p in self.patrons if p.patron_id == almost_overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == almost_overdue.item_record_id)

            # Determine notification time based on delivery method
            # Email/Mail: 8:00 AM (on the day 3 days before due)
            # SMS/Voice: 07:30 or 08:03 (on the day 3 days before due - Shoutbomb upload times)
            notification_date = almost_overdue.due_date - timedelta(days=3)

            if patron.delivery_option_id in [1, 2]:  # Email or Mail
                # Email/Mail sent at 8:00 AM
                notice_date = self.generate_batch_time(notification_date, hour=8, minute=0)
            else:  # SMS (8) or Voice (3)
                # SMS/Voice sent at 07:30 or 08:03 (Shoutbomb upload times)
                if random.random() < 0.5:
                    # Courtesy batch at 07:30
                    notice_date = self.generate_batch_time(notification_date, hour=7, minute=30)
                else:
                    # Renew batch at 08:03
                    notice_date = self.generate_batch_time(notification_date, hour=8, minute=3)

            # Determine status
            if patron.delivery_option_id == 2:  # Email
                status_id = STATUS_EMAIL_SUCCESS if random.random() > 0.05 else STATUS_EMAIL_FAILED
                delivery_string = patron.email
            elif patron.delivery_option_id == 8:  # SMS
                status_id = STATUS_SENT
                delivery_string = patron.phone
            elif patron.delivery_option_id == 3:  # Voice
                status_id = random.choices([STATUS_CALL_VOICE, STATUS_CALL_MACHINE], weights=[0.7, 0.3])[0]
                delivery_string = patron.phone
            else:  # Mail (1)
                status_id = STATUS_MAIL_PRINTED
                delivery_string = ""

            history.append({
                "PatronId": patron.patron_id,
                "ItemRecordId": item.item_record_id,
                "TxnId": None,
                "NotificationTypeId": NOTIF_TYPE_ALMOST_OVERDUE,
                "ReportingOrgId": 3,
                "DeliveryOptionId": patron.delivery_option_id,
                "NoticeDate": notice_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-5],
                "Amount": 0,
                "NotificationStatusId": status_id,
                "Title": item.title,
            })

            batch_key = notice_date.replace(second=0, microsecond=0)
            if batch_key not in self.notification_batches:
                self.notification_batches[batch_key] = []
            self.notification_batches[batch_key].append({
                "patron_id": patron.patron_id,
                "type": "almost_overdue",
                "delivery_option": patron.delivery_option_id,
                "delivery_string": delivery_string,
                "status": status_id,
            })

        # Create notification logs (combined by patron per batch)
        log_id = 1
        for batch_time, notifications in self.notification_batches.items():
            # Group by patron
            patron_notifications = {}
            for notif in notifications:
                patron_id = notif["patron_id"]
                if patron_id not in patron_notifications:
                    patron_notifications[patron_id] = []
                patron_notifications[patron_id].append(notif)

            # Create log entry per patron
            for patron_id, notifs in patron_notifications.items():
                patron = next(p for p in self.patrons if p.patron_id == patron_id)

                holds_count = sum(1 for n in notifs if n["type"] == "hold")
                overdues_count = sum(1 for n in notifs if n["type"] == "overdue")

                # Use first notification's delivery info
                first_notif = notifs[0]

                logs.append({
                    "PatronID": patron_id,
                    "NotificationDateTime": batch_time.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                    "NotificationTypeID": NOTIF_TYPE_HOLD if holds_count > 0 else NOTIF_TYPE_OVERDUE_1ST,
                    "DeliveryOptionID": first_notif["delivery_option"],
                    "DeliveryString": first_notif["delivery_string"],
                    "OverduesCount": overdues_count,
                    "HoldsCount": holds_count,
                    "CancelsCount": 0,
                    "RecallsCount": 0,
                    "NotificationStatusID": first_notif["status"],
                    "Details": "",
                    "RoutingsCount": 0,
                    "ReportingOrgID": 3,
                    "PatronBarcode": patron.barcode,
                    "Reported": 1,
                    "Overdues2ndCount": None,
                    "Overdues3rdCount": None,
                    "BillsCount": None,
                    "LanguageID": None,
                    "CarrierName": None,
                    "ManualBillCount": None,
                    "NotificationLogID": log_id,
                })
                log_id += 1

        print(f"  Generated {len(history)} notification history records")
        print(f"  Generated {len(logs)} notification log records")

        return history, logs

    def generate_sys_hold_requests(self):
        """Generate SysHoldRequests for all holds"""
        print("Generating SysHoldRequests...")

        requests = []
        for hold in self.holds:
            patron = next(p for p in self.patrons if p.patron_id == hold.patron_id)
            item = next(i for i in self.items if i.item_record_id == hold.item_record_id)

            # Generate UUID-style message ID
            import uuid
            message_id = str(uuid.uuid4()).upper()

            requests.append({
                "SysHoldRequestID": hold.sys_hold_request_id,
                "Sequence": hold.sys_hold_request_id,
                "PatronID": patron.patron_id,
                "PickupBranchID": hold.pickup_org_id,
                "SysHoldStatusID": 1,  # Active
                "RTFCyclesPrimary": 0,
                "CreationDate": hold.creation_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "ActivationDate": hold.creation_date.strftime("%Y-%m-%d 00:00:00.000"),
                "ExpirationDate": (hold.hold_till_date + timedelta(days=365)).strftime("%Y-%m-%d 23:59:59.000"),
                "LastStatusTransitionDate": hold.hold_notification_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "LCCN": "",
                "PublicationYear": random.randint(2015, 2024),
                "ISBN": "",
                "ISSN": "",
                "ItemBarcode": item.barcode,
                "BibliographicRecordID": item.item_record_id + 500000,  # Offset for bib IDs
                "TrappingItemRecordID": item.item_record_id,
                "StaffDisplayNotes": "",
                "NonPublicNotes": "",
                "PatronNotes": "",
                "MessageID": message_id,
                "HoldTillDate": hold.hold_till_date.strftime("%Y-%m-%d 23:59:59.000"),
                "Origin": 1,
                "Series": "",
                "Pages": "",
                "CreatorID": 1,
                "ModifierID": 1,
                "ModificationDate": hold.hold_notification_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "Publisher": "",
                "Edition": "",
                "VolumeNumber": "",
                "HoldNotificationDate": hold.hold_notification_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "DeliveryOptionID": hold.delivery_option_id,
                "Suspended": 0,
                "UnlockedRequest": 0,
                "RTFCyclesSecondary": 0,
                "PrimarySecondaryFlag": 1,
                "PrimaryRandomStartSequence": 1,
                "SecondaryRandomStartSequence": random.randint(1, 100),
                "PrimaryMARCTOMID": None,
                "ISBNNormalized": "",
                "ISSNNormalized": "",
                "Designation": "",
                "ItemLevelHold": 0,
                "ItemLevelHoldItemRecordID": None,
                "BorrowByMailRequest": 0,
                "PACDisplayNotes": "",
                "TrackingInfo": None,
                "HoldNotification2ndDate": None,
                "ConstituentBibRecordID": None,
                "PrimaryRTFBeginDate": hold.creation_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "PrimaryRTFEndDate": hold.creation_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "SecondaryRTFBeginDate": None,
                "SecondaryRTFEndDate": None,
                "NotSuppliedReasonCodeID": None,
                "NewPickupBranchID": None,
                "HoldPickupAreaID": None,
                "NewHoldPickupAreaID": None,
                "FeeInserted": 0,
            })

        print(f"  Generated {len(requests)} SysHoldRequests")
        return requests

    def generate_notification_queue(self):
        """Generate NotificationQueue for holds and overdues"""
        print("Generating NotificationQueue...")

        queue = []

        # Add hold notifications to queue (SMS/Voice only - email/mail don't use Shoutbomb)
        for hold in self.holds:
            patron = next(p for p in self.patrons if p.patron_id == hold.patron_id)
            item = next(i for i in self.items if i.item_record_id == hold.item_record_id)

            # Only SMS (8) and Voice (3) go through Shoutbomb
            if hold.delivery_option_id not in [3, 8]:
                continue

            queue.append({
                "ItemRecordID": item.item_record_id,
                "PatronID": patron.patron_id,
                "ItemBarcode": item.barcode,
                "DueDate": None,
                "BrowseTitle": item.title,
                "BrowseAuthor": item.author,
                "ItemCallNumber": item.call_number,
                "Price": f"{item.price:.2f}",
                "Abbreviation": "DCPL",
                "Name": "Daviess County Public Library",
                "PhoneNumberOne": "270-684-0211 x262",
                "LoaningOrganizationID": 3,
                "FineCodeID": None,
                "LoanUnits": None,
                "BillingNotice": 0,
                "ReplacementCost": "0.00",
                "OverdueCharge": "0.00",
                "ReportingOrgID": 3,
                "DeliveryOptionID": hold.delivery_option_id,
                "ReturnAddressOrgID": 3,
                "NotificationTypeID": NOTIF_TYPE_HOLD,
                "IncludeClaimedItems": 1,
                "ProcessingCharge": "0.00",
                "AdminLanguageID": 1033,
                "OverdueNoticeID": None,
                "BaseProcessingCharge": "0.00",
                "BaseReplacementCost": "0.00",
            })

        # Add overdue notifications to queue (SMS/Voice only)
        for overdue in self.overdues:
            patron = next(p for p in self.patrons if p.patron_id == overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == overdue.item_record_id)

            # Only SMS (8) and Voice (3) go through Shoutbomb
            if patron.delivery_option_id not in [3, 8]:
                continue

            queue.append({
                "ItemRecordID": item.item_record_id,
                "PatronID": patron.patron_id,
                "ItemBarcode": item.barcode,
                "DueDate": overdue.due_date.strftime("%Y-%m-%d 23:59:59.000"),
                "BrowseTitle": item.title,
                "BrowseAuthor": item.author,
                "ItemCallNumber": item.call_number,
                "Price": f"{item.price:.2f}",
                "Abbreviation": "DCPL",
                "Name": "Daviess County Public Library",
                "PhoneNumberOne": "270-684-0211 x262",
                "LoaningOrganizationID": 3,
                "FineCodeID": 5,
                "LoanUnits": 1,
                "BillingNotice": 0,
                "ReplacementCost": "0.00",
                "OverdueCharge": "0.00",
                "ReportingOrgID": 3,
                "DeliveryOptionID": patron.delivery_option_id,
                "ReturnAddressOrgID": 3,
                "NotificationTypeID": NOTIF_TYPE_OVERDUE_1ST,
                "IncludeClaimedItems": 1,
                "ProcessingCharge": "0.00",
                "AdminLanguageID": 1033,
                "OverdueNoticeID": overdue.overdue_notice_count + 1,
                "BaseProcessingCharge": "0.00",
                "BaseReplacementCost": "0.00",
            })

        # Add almost overdue notifications to queue (SMS/Voice only)
        for almost_overdue in self.almost_overdues:
            patron = next(p for p in self.patrons if p.patron_id == almost_overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == almost_overdue.item_record_id)

            # Only SMS (8) and Voice (3) go through Shoutbomb
            if patron.delivery_option_id not in [3, 8]:
                continue

            queue.append({
                "ItemRecordID": item.item_record_id,
                "PatronID": patron.patron_id,
                "ItemBarcode": item.barcode,
                "DueDate": almost_overdue.due_date.strftime("%Y-%m-%d 23:59:59.000"),
                "BrowseTitle": item.title,
                "BrowseAuthor": item.author,
                "ItemCallNumber": item.call_number,
                "Price": f"{item.price:.2f}",
                "Abbreviation": "DCPL",
                "Name": "Daviess County Public Library",
                "PhoneNumberOne": "270-684-0211 x262",
                "LoaningOrganizationID": 3,
                "FineCodeID": None,
                "LoanUnits": None,
                "BillingNotice": 0,
                "ReplacementCost": "0.00",
                "OverdueCharge": "0.00",
                "ReportingOrgID": 3,
                "DeliveryOptionID": patron.delivery_option_id,
                "ReturnAddressOrgID": 3,
                "NotificationTypeID": NOTIF_TYPE_ALMOST_OVERDUE,
                "IncludeClaimedItems": 1,
                "ProcessingCharge": "0.00",
                "AdminLanguageID": 1033,
                "OverdueNoticeID": None,
                "BaseProcessingCharge": "0.00",
                "BaseReplacementCost": "0.00",
            })

        print(f"  Generated {len(queue)} NotificationQueue entries")
        return queue

    def generate_item_checkouts(self):
        """Generate ItemCheckouts table for all checked-out items"""
        print("Generating ItemCheckouts...")

        checkouts = []

        # Add overdues (already checked out, past due)
        for overdue in self.overdues:
            patron = next(p for p in self.patrons if p.patron_id == overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == overdue.item_record_id)

            checkouts.append({
                "PatronID": patron.patron_id,
                "ItemRecordID": item.item_record_id,
                "ItemBarcode": item.barcode,
                "CheckOutDate": overdue.checkout_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "DueDate": overdue.due_date.strftime("%Y-%m-%d 23:59:59.000"),
                "Renewals": overdue.renewals,
                "RenewalLimit": 2,
                "PatronBarcode": patron.barcode,
            })

        # Add almost overdues (checked out, due in 3 days)
        for almost_overdue in self.almost_overdues:
            patron = next(p for p in self.patrons if p.patron_id == almost_overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == almost_overdue.item_record_id)

            checkouts.append({
                "PatronID": patron.patron_id,
                "ItemRecordID": item.item_record_id,
                "ItemBarcode": item.barcode,
                "CheckOutDate": almost_overdue.checkout_date.strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "DueDate": almost_overdue.due_date.strftime("%Y-%m-%d 23:59:59.000"),
                "Renewals": almost_overdue.renewals,
                "RenewalLimit": almost_overdue.renewal_limit,
                "PatronBarcode": patron.barcode,
            })

        print(f"  Generated {len(checkouts)} ItemCheckouts")
        return checkouts

    def generate_overdue_notices(self):
        """Generate OverdueNotices view"""
        print("Generating OverdueNotices...")

        notices = []
        for overdue in self.overdues:
            patron = next(p for p in self.patrons if p.patron_id == overdue.patron_id)
            item = next(i for i in self.items if i.item_record_id == overdue.item_record_id)

            notices.append({
                "ItemRecordID": item.item_record_id,
                "PatronID": patron.patron_id,
                "ItemBarcode": item.barcode,
                "DueDate": overdue.due_date.strftime("%Y-%m-%d 23:59:59.000"),
                "BrowseTitle": item.title,
                "BrowseAuthor": item.author,
                "ItemCallNumber": item.call_number,
                "Price": f"{item.price:.2f}",
                "Abbreviation": "DCPL",
                "Name": "Daviess County Public Library",
                "PhoneNumberOne": "270-684-0211 x262",
                "LoaningOrganizationID": 3,
                "FineCodeID": 5,
                "LoanUnits": 1,
                "BillingNotice": 0,
                "ReplacementCost": "0.00",
                "OverdueCharge": "0.00",
                "ReportingOrgID": 3,
                "DeliveryOptionID": patron.delivery_option_id,
                "ReturnAddressOrgID": 3,
                "NotificationTypeID": NOTIF_TYPE_OVERDUE_1ST,
                "IncludeClaimedItems": 1,
                "ProcessingCharge": "0.00",
                "AdminLanguageID": 1033,
                "OverdueNoticeID": overdue.overdue_notice_count + 1,
                "BaseProcessingCharge": "0.00",
                "BaseReplacementCost": "0.00",
            })

        print(f"  Generated {len(notices)} OverdueNotices")
        return notices

    def write_csv(self, filename: str, headers: List[str], rows: List[Dict], delimiter='\t'):
        """Write CSV file"""
        filepath = SAMPLE_DATA_DIR / filename
        with open(filepath, 'w', encoding='utf-8', newline='') as f:
            writer = csv.DictWriter(f, fieldnames=headers, delimiter=delimiter, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(rows)
        print(f"  Wrote {filename} ({len(rows)} rows)")

    def generate_lookup_tables(self):
        """Generate all lookup/reference tables"""
        print("Generating lookup tables...")

        # Organizations
        organizations = [
            {"OrganizationID": 1, "ParentOrganizationID": None, "OrganizationCodeID": 1, "Name": "Daviess County", "Abbreviation": "DC", "SA_ContactPersonID": 3, "CreatorID": 1, "ModifierID": 56, "CreationDate": None, "ModificationDate": "2020-07-28 13:00:35.500", "DisplayName": "Daviess County"},
            {"OrganizationID": 2, "ParentOrganizationID": 1, "OrganizationCodeID": 2, "Name": "Daviess County Public", "Abbreviation": "DCP", "SA_ContactPersonID": 3, "CreatorID": 1, "ModifierID": 11, "CreationDate": None, "ModificationDate": "2019-09-25 11:05:49.463", "DisplayName": "Daviess County Public Library"},
            {"OrganizationID": 3, "ParentOrganizationID": 2, "OrganizationCodeID": 3, "Name": "Daviess County Public Library", "Abbreviation": "DCPL", "SA_ContactPersonID": 3, "CreatorID": 1, "ModifierID": 11, "CreationDate": None, "ModificationDate": "2019-09-25 11:07:16.347", "DisplayName": "Daviess County Public Library"},
        ]
        self.write_csv("Polaris.Polaris.Organizations.csv", [
            "OrganizationID", "ParentOrganizationID", "OrganizationCodeID", "Name", "Abbreviation",
            "SA_ContactPersonID", "CreatorID", "ModifierID", "CreationDate", "ModificationDate", "DisplayName"
        ], organizations)

        # ItemStatuses
        item_statuses = [
            {"ItemStatusID": 1, "Description": "Available", "Name": "In", "BannerText": "Available"},
            {"ItemStatusID": 2, "Description": "Out", "Name": "Out", "BannerText": "Out"},
            {"ItemStatusID": 3, "Description": "Out-ILL", "Name": "Out-ILL", "BannerText": "Out-ILL"},
            {"ItemStatusID": 4, "Description": "Held", "Name": "Held", "BannerText": "Held"},
            {"ItemStatusID": 5, "Description": "Transferred", "Name": "Transferred", "BannerText": "Transferred"},
            {"ItemStatusID": 6, "Description": "Outreach", "Name": "In-Transit", "BannerText": "Outreach"},
            {"ItemStatusID": 7, "Description": "Lost", "Name": "Lost", "BannerText": "Lost"},
            {"ItemStatusID": 8, "Description": "Claimed Returned", "Name": "Claim Returned", "BannerText": "Claimed"},
            {"ItemStatusID": 19, "Description": "Shelving Cart", "Name": "Shelving", "BannerText": "Shelving Cart"},
            {"ItemStatusID": 20, "Description": "Non-circulating", "Name": "Non-circulating", "BannerText": "Non-circulating"},
        ]
        self.write_csv("Polaris.Polaris.ItemStatuses.csv", [
            "ItemStatusID", "Description", "Name", "BannerText"
        ], item_statuses)

        # MaterialTypes
        material_types = [
            {"MaterialTypeID": 1, "Description": "Books", "MinimumAge": 0},
            {"MaterialTypeID": 14, "Description": "Juvenile DVD/Video", "MinimumAge": 0},
            {"MaterialTypeID": 21, "Description": "CD", "MinimumAge": 0},
            {"MaterialTypeID": 24, "Description": "DVD/Video", "MinimumAge": 0},
            {"MaterialTypeID": 26, "Description": "DVD/Video - Restricted", "MinimumAge": 0},
            {"MaterialTypeID": 34, "Description": "Blu-ray Disc", "MinimumAge": 0},
            {"MaterialTypeID": 37, "Description": "Video Game - E", "MinimumAge": 0},
            {"MaterialTypeID": 38, "Description": "Video Game - Restricted", "MinimumAge": 0},
            {"MaterialTypeID": 45, "Description": "Audiobook CD", "MinimumAge": 0},
            {"MaterialTypeID": 49, "Description": "Juvenile Book", "MinimumAge": 0},
        ]
        self.write_csv("Results.Polaris.MaterialTypes.csv", [
            "MaterialTypeID", "Description", "MinimumAge"
        ], material_types)

        # FineCodes
        fine_codes = [
            {"FineCodeID": 1, "Description": ".20-2.00 max"},
            {"FineCodeID": 2, "Description": ".20-10.00 max"},
            {"FineCodeID": 3, "Description": "1.00-10.00 max"},
            {"FineCodeID": 4, "Description": "1.00-5.00 max"},
            {"FineCodeID": 5, "Description": "0-0.00"},
            {"FineCodeID": 6, "Description": ".50-10.00 max"},
            {"FineCodeID": 7, "Description": ".20-5.00 max"},
        ]
        self.write_csv("Polaris.Polaris.FineCodes.csv", [
            "FineCodeID", "Description"
        ], fine_codes)

        # LoanPeriodCodes
        loan_period_codes = [
            {"LoanPeriodCodeID": 1, "Description": "28 days"},
            {"LoanPeriodCodeID": 2, "Description": "14 days"},
            {"LoanPeriodCodeID": 3, "Description": "7 days"},
            {"LoanPeriodCodeID": 4, "Description": "0 days"},
            {"LoanPeriodCodeID": 6, "Description": "21 days"},
            {"LoanPeriodCodeID": 7, "Description": "2 days"},
        ]
        self.write_csv("Polaris.Polaris.LoanPeriodCodes.csv", [
            "LoanPeriodCodeID", "Description"
        ], loan_period_codes)

        # RecordStatuses
        record_statuses = [
            {"RecordStatusID": 1, "RecordStatusName": "Final"},
            {"RecordStatusID": 2, "RecordStatusName": "Provisional"},
            {"RecordStatusID": 3, "RecordStatusName": "Secured"},
            {"RecordStatusID": 4, "RecordStatusName": "Deleted"},
        ]
        self.write_csv("Polaris.Polaris.RecordStatuses.csv", [
            "RecordStatusID", "RecordStatusName"
        ], record_statuses)

        # ShelfLocations (subset)
        shelf_locations = [
            {"ShelfLocationID": 1, "OrganizationID": 3, "Description": "1st Floor"},
            {"ShelfLocationID": 2, "OrganizationID": 3, "Description": "1st Floor-Reference"},
            {"ShelfLocationID": 3, "OrganizationID": 3, "Description": "1st Floor-Kentucky Room"},
            {"ShelfLocationID": 4, "OrganizationID": 3, "Description": "2nd Floor"},
            {"ShelfLocationID": 5, "OrganizationID": 3, "Description": "2nd Floor-Childrens' Area"},
            {"ShelfLocationID": 29, "OrganizationID": 3, "Description": "2nd Floor Wall 2"},
            {"ShelfLocationID": 73, "OrganizationID": 3, "Description": "2nd Floor Reference"},
            {"ShelfLocationID": 76, "OrganizationID": 3, "Description": "KR Storage"},
            {"ShelfLocationID": 79, "OrganizationID": 3, "Description": "2nd Floor Young Adult"},
        ]
        self.write_csv("Polaris.Polaris.ShelfLocations.csv", [
            "ShelfLocationID", "OrganizationID", "Description"
        ], shelf_locations)

        # StatisticalCodes (subset)
        statistical_codes = [
            {"StatisticalCodeID": 1, "OrganizationID": 3, "Description": "Adult Fiction"},
            {"StatisticalCodeID": 2, "OrganizationID": 3, "Description": "Adult Fiction-Mystery"},
            {"StatisticalCodeID": 5, "OrganizationID": 3, "Description": "Adult Nonfiction"},
            {"StatisticalCodeID": 12, "OrganizationID": 3, "Description": "AV DVD-Feature Film"},
            {"StatisticalCodeID": 27, "OrganizationID": 3, "Description": "Juvenile Fiction"},
            {"StatisticalCodeID": 31, "OrganizationID": 3, "Description": "Juvenile Nonfiction"},
            {"StatisticalCodeID": 39, "OrganizationID": 3, "Description": "Kentucky Room Genealogy"},
            {"StatisticalCodeID": 43, "OrganizationID": 3, "Description": "Large Print Fiction"},
            {"StatisticalCodeID": 49, "OrganizationID": 3, "Description": "Magazine"},
            {"StatisticalCodeID": 61, "OrganizationID": 3, "Description": "AV Blu-Ray Disc"},
        ]
        self.write_csv("Polaris.Polaris.StatisticalCodes.csv", [
            "StatisticalCodeID", "OrganizationID", "Description"
        ], statistical_codes)

        # Workstations (subset)
        workstations = [
            {"WorkstationID": 1, "OrganizationID": 1, "DisplayName": "Anonymous OPAC Workstation", "ComputerName": None, "CreatorID": 1, "ModifierID": None, "CreationDate": None, "ModificationDate": None, "Enabled": 0, "Status": 0, "StatusDate": None, "NetworkDomainID": None, "LeapAllowed": 0, "TerminalServer": 0},
            {"WorkstationID": 3, "OrganizationID": 3, "DisplayName": "DCPLPRO", "ComputerName": "DCPLPRO", "CreatorID": 1, "ModifierID": 1, "CreationDate": "2008-07-09 10:59:08.747", "ModificationDate": "2021-06-07 08:25:36.657", "Enabled": 1, "Status": 1, "StatusDate": "2025-11-04 08:27:46.393", "NetworkDomainID": None, "LeapAllowed": 1, "TerminalServer": 1},
            {"WorkstationID": 60, "OrganizationID": 3, "DisplayName": "CircIT Check-in 1", "ComputerName": "SWS001620", "CreatorID": 1, "ModifierID": 21, "CreationDate": "2009-06-11 15:53:40.837", "ModificationDate": "2013-01-14 06:58:46.863", "Enabled": 1, "Status": 1, "StatusDate": "2024-07-11 07:59:46.120", "NetworkDomainID": None, "LeapAllowed": 0, "TerminalServer": 0},
        ]
        self.write_csv("Polaris.Polaris.Workstations.csv", [
            "WorkstationID", "OrganizationID", "DisplayName", "ComputerName", "CreatorID", "ModifierID",
            "CreationDate", "ModificationDate", "Enabled", "Status", "StatusDate", "NetworkDomainID",
            "LeapAllowed", "TerminalServer"
        ], workstations)

        print("  Lookup tables generated")

    def generate_all_csvs(self):
        """Generate all CSV files"""
        print("\nWriting CSV files...")

        # Generate lookup/reference tables first
        self.generate_lookup_tables()

        # Patrons
        patron_rows = []
        for p in self.patrons:
            patron_rows.append({
                "PatronID": p.patron_id,
                "PatronCodeID": 3,
                "OrganizationID": 3,
                "CreatorID": 1,
                "ModifierID": 17,
                "Barcode": p.barcode,
                "SystemBlocks": 0,
                "YTDCircCount": 0,
                "LifetimeCircCount": random.randint(0, 500),
                "LastActivityDate": (TODAY - timedelta(days=random.randint(1, 30))).strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "ClaimCount": 0,
                "LostItemCount": 0,
                "ChargesAmount": "0.0000",
                "CreditsAmount": "0.0000",
                "RecordStatusID": 1,
                "RecordStatusDate": "2015-06-09 08:36:30.867",
                "YTDYouSavedAmount": "0.0000",
                "LifetimeYouSavedAmount": f"{random.uniform(0, 1000):.4f}",
            })

        self.write_csv("Polaris.Polaris.Patrons.csv", [
            "PatronID", "PatronCodeID", "OrganizationID", "CreatorID", "ModifierID",
            "Barcode", "SystemBlocks", "YTDCircCount", "LifetimeCircCount", "LastActivityDate",
            "ClaimCount", "LostItemCount", "ChargesAmount", "CreditsAmount", "RecordStatusID",
            "RecordStatusDate", "YTDYouSavedAmount", "LifetimeYouSavedAmount"
        ], patron_rows)

        # PatronRegistration
        registration_rows = []
        for p in self.patrons:
            registration_rows.append({
                "PatronID": p.patron_id,
                "LanguageID": 1,
                "NameFirst": p.first_name,
                "NameLast": p.last_name,
                "NameMiddle": p.middle_name,
                "NameTitle": "",
                "NameSuffix": "",
                "PhoneVoice1": p.phone,
                "PhoneVoice2": "",
                "PhoneVoice3": "",
                "EmailAddress": p.email,
                "EntryDate": "2009-09-04 12:44:05.483",
                "ExpirationDate": (TODAY + timedelta(days=365)).strftime("%Y-%m-%d 00:00:00.000"),
                "AddrCheckDate": (TODAY - timedelta(days=30)).strftime("%Y-%m-%d 00:00:00.000"),
                "UpdateDate": (TODAY - timedelta(days=random.randint(1, 30))).strftime("%Y-%m-%d %H:%M:%S.%f")[:-4],
                "User1": "",
                "User2": "",
                "User3": "",
                "User4": "",
                "User5": "",
                "Birthdate": fake.date_of_birth(minimum_age=18, maximum_age=80).strftime("%Y-%m-%d 00:00:00.000"),
                "RegistrationDate": "2006-08-22 00:00:00.000",
                "FormerID": "",
                "ReadingList": random.choice([0, 1]),
                "PhoneFAX": "",
                "DeliveryOptionID": p.delivery_option_id,
                "StatisticalClassID": random.choice([1, 8, 13]),
                "CollectionExempt": 0,
                "AltEmailAddress": "",
                "ExcludeFromOverdues": 0,
                "SDIEmailAddress": "",
                "SDIEmailFormatID": None,
                "SDIPositiveAssent": None,
                "SDIPositiveAssentDate": None,
                "DeletionExempt": 0,
                "PatronFullName": p.full_name,
                "ExcludeFromHolds": 0,
                "ExcludeFromBills": 0,
                "EmailFormatID": 1,
                "PatronFirstLastName": p.first_last_name,
                "Username": "",
                "MergeDate": None,
                "MergeUserID": None,
                "MergeBarcode": None,
                "EnableSMS": 1 if p.delivery_option_id == 8 else 0,
                "RequestPickupBranchID": 3,
                "Phone1CarrierID": None,
                "Phone2CarrierID": None,
                "Phone3CarrierID": None,
                "eReceiptOptionID": 1,
                "TxtPhoneNumber": 1 if p.delivery_option_id == 8 else None,
                "ExcludeFromAlmostOverdueAutoRenew": 0,
                "ExcludeFromPatronRecExpiration": 0,
                "ExcludeFromInactivePatron": 0,
                "DoNotShowEReceiptPrompt": 0,
                "PasswordHash": p.password_hash,
                "ObfuscatedPassword": p.obfuscated_password,
                "NameTitleID": None,
                "RBdigitalPatronID": None,
                "GenderID": 1,
                "LegalNameFirst": None,
                "LegalNameLast": None,
                "LegalNameMiddle": None,
                "LegalFullName": None,
                "UseLegalNameOnNotices": 0,
                "EnablePush": 0,
                "StaffAcceptedUseSingleName": 0,
                "ExtendedLoanPeriods": 0,
                "IncreasedCheckOutLimits": 0,
            })

        self.write_csv("Polaris.Polaris.PatronRegistration.csv", [
            "PatronID", "LanguageID", "NameFirst", "NameLast", "NameMiddle", "NameTitle", "NameSuffix",
            "PhoneVoice1", "PhoneVoice2", "PhoneVoice3", "EmailAddress", "EntryDate", "ExpirationDate",
            "AddrCheckDate", "UpdateDate", "User1", "User2", "User3", "User4", "User5",
            "Birthdate", "RegistrationDate", "FormerID", "ReadingList", "PhoneFAX", "DeliveryOptionID",
            "StatisticalClassID", "CollectionExempt", "AltEmailAddress", "ExcludeFromOverdues",
            "SDIEmailAddress", "SDIEmailFormatID", "SDIPositiveAssent", "SDIPositiveAssentDate",
            "DeletionExempt", "PatronFullName", "ExcludeFromHolds", "ExcludeFromBills", "EmailFormatID",
            "PatronFirstLastName", "Username", "MergeDate", "MergeUserID", "MergeBarcode", "EnableSMS",
            "RequestPickupBranchID", "Phone1CarrierID", "Phone2CarrierID", "Phone3CarrierID",
            "eReceiptOptionID", "TxtPhoneNumber", "ExcludeFromAlmostOverdueAutoRenew",
            "ExcludeFromPatronRecExpiration", "ExcludeFromInactivePatron", "DoNotShowEReceiptPrompt",
            "PasswordHash", "ObfuscatedPassword", "NameTitleID", "RBdigitalPatronID", "GenderID",
            "LegalNameFirst", "LegalNameLast", "LegalNameMiddle", "LegalFullName",
            "UseLegalNameOnNotices", "EnablePush", "StaffAcceptedUseSingleName",
            "ExtendedLoanPeriods", "IncreasedCheckOutLimits"
        ], registration_rows)

        # HoldNotices
        hold_notices_rows = []
        for hold in self.holds:
            patron = next(p for p in self.patrons if p.patron_id == hold.patron_id)
            item = next(i for i in self.items if i.item_record_id == hold.item_record_id)

            hold_notices_rows.append({
                "ItemRecordID": item.item_record_id,
                "AssignedBranchID": 3,
                "PickupOrganizationID": hold.pickup_org_id,
                "PatronID": patron.patron_id,
                "ItemBarcode": item.barcode,
                "BrowseTitle": item.title,
                "BrowseAuthor": item.author,
                "ItemCallNumber": item.call_number,
                "Price": f"{item.price:.2f}",
                "Abbreviation": "DCPL",
                "Name": "Daviess County Public Library",
                "PhoneNumberOne": "270-684-0211 x262",
                "DeliveryOptionID": hold.delivery_option_id,
                "HoldTillDate": hold.hold_till_date.strftime("%Y-%m-%d 23:59:59.000"),
                "ItemFormatID": 49,
                "AdminLanguageID": 1033,
                "NotificationTypeID": 2,
                "HoldPickupAreaID": None,
            })

        self.write_csv("Results.Polaris.HoldNotices.csv", [
            "ItemRecordID", "AssignedBranchID", "PickupOrganizationID", "PatronID", "ItemBarcode",
            "BrowseTitle", "BrowseAuthor", "ItemCallNumber", "Price", "Abbreviation", "Name",
            "PhoneNumberOne", "DeliveryOptionID", "HoldTillDate", "ItemFormatID", "AdminLanguageID",
            "NotificationTypeID", "HoldPickupAreaID"
        ], hold_notices_rows)

        # Generate and write NotificationHistory and Logs
        history, logs = self.generate_notification_history()

        self.write_csv("Results.Polaris.NotificationHistory.csv", [
            "PatronId", "ItemRecordId", "TxnId", "NotificationTypeId", "ReportingOrgId",
            "DeliveryOptionId", "NoticeDate", "Amount", "NotificationStatusId", "Title"
        ], history, delimiter=',')

        self.write_csv("PolarisTransactions.Polaris.NotificationLogs.csv", [
            "PatronID", "NotificationDateTime", "NotificationTypeID", "DeliveryOptionID",
            "DeliveryString", "OverduesCount", "HoldsCount", "CancelsCount", "RecallsCount",
            "NotificationStatusID", "Details", "RoutingsCount", "ReportingOrgID", "PatronBarcode",
            "Reported", "Overdues2ndCount", "Overdues3rdCount", "BillsCount", "LanguageID",
            "CarrierName", "ManualBillCount", "NotificationLogID"
        ], logs)

        # Generate and write SysHoldRequests
        sys_hold_requests = self.generate_sys_hold_requests()
        self.write_csv("Polaris.Polaris.SysHoldRequests.csv", [
            "SysHoldRequestID", "Sequence", "PatronID", "PickupBranchID", "SysHoldStatusID",
            "RTFCyclesPrimary", "CreationDate", "ActivationDate", "ExpirationDate",
            "LastStatusTransitionDate", "LCCN", "PublicationYear", "ISBN", "ISSN", "ItemBarcode",
            "BibliographicRecordID", "TrappingItemRecordID", "StaffDisplayNotes", "NonPublicNotes",
            "PatronNotes", "MessageID", "HoldTillDate", "Origin", "Series", "Pages", "CreatorID",
            "ModifierID", "ModificationDate", "Publisher", "Edition", "VolumeNumber",
            "HoldNotificationDate", "DeliveryOptionID", "Suspended", "UnlockedRequest",
            "RTFCyclesSecondary", "PrimarySecondaryFlag", "PrimaryRandomStartSequence",
            "SecondaryRandomStartSequence", "PrimaryMARCTOMID", "ISBNNormalized", "ISSNNormalized",
            "Designation", "ItemLevelHold", "ItemLevelHoldItemRecordID", "BorrowByMailRequest",
            "PACDisplayNotes", "TrackingInfo", "HoldNotification2ndDate", "ConstituentBibRecordID",
            "PrimaryRTFBeginDate", "PrimaryRTFEndDate", "SecondaryRTFBeginDate", "SecondaryRTFEndDate",
            "NotSuppliedReasonCodeID", "NewPickupBranchID", "HoldPickupAreaID", "NewHoldPickupAreaID",
            "FeeInserted"
        ], sys_hold_requests)

        # Generate and write NotificationQueue
        notification_queue = self.generate_notification_queue()
        self.write_csv("Results.Polaris.NotificationQueue.csv", [
            "ItemRecordID", "PatronID", "ItemBarcode", "DueDate", "BrowseTitle", "BrowseAuthor",
            "ItemCallNumber", "Price", "Abbreviation", "Name", "PhoneNumberOne", "LoaningOrganizationID",
            "FineCodeID", "LoanUnits", "BillingNotice", "ReplacementCost", "OverdueCharge",
            "ReportingOrgID", "DeliveryOptionID", "ReturnAddressOrgID", "NotificationTypeID",
            "IncludeClaimedItems", "ProcessingCharge", "AdminLanguageID", "OverdueNoticeID",
            "BaseProcessingCharge", "BaseReplacementCost"
        ], notification_queue)

        # Generate and write OverdueNotices
        overdue_notices = self.generate_overdue_notices()
        self.write_csv("Results.Polaris.OverdueNotices.csv", [
            "ItemRecordID", "PatronID", "ItemBarcode", "DueDate", "BrowseTitle", "BrowseAuthor",
            "ItemCallNumber", "Price", "Abbreviation", "Name", "PhoneNumberOne", "LoaningOrganizationID",
            "FineCodeID", "LoanUnits", "BillingNotice", "ReplacementCost", "OverdueCharge",
            "ReportingOrgID", "DeliveryOptionID", "ReturnAddressOrgID", "NotificationTypeID",
            "IncludeClaimedItems", "ProcessingCharge", "AdminLanguageID", "OverdueNoticeID",
            "BaseProcessingCharge", "BaseReplacementCost"
        ], overdue_notices)

        # Generate and write ItemCheckouts
        item_checkouts = self.generate_item_checkouts()
        self.write_csv("Polaris.Polaris.ItemCheckouts.csv", [
            "PatronID", "ItemRecordID", "ItemBarcode", "CheckOutDate", "DueDate",
            "Renewals", "RenewalLimit", "PatronBarcode"
        ], item_checkouts)

        print("\n✅ All CSV files generated successfully!")


def main():
    """Main execution"""
    print("=" * 60)
    print("Comprehensive Polaris Sample Data Generator")
    print("=" * 60)
    print()

    generator = DataGenerator()

    # Generate all data
    generator.generate_patrons()
    generator.generate_items()
    generator.generate_holds_and_overdues()

    # Write CSV files
    generator.generate_all_csvs()

    # Summary
    print("\n" + "=" * 60)
    print("GENERATION SUMMARY")
    print("=" * 60)
    print(f"Patrons:          {len(generator.patrons)}")
    print(f"Items:            {len(generator.items)}")
    print(f"Holds:            {len(generator.holds)}")
    print(f"Overdues:         {len(generator.overdues)}")
    print(f"Almost Overdues:  {len(generator.almost_overdues)}")
    print(f"Batch Times:      {len(generator.notification_batches)}")
    print()

    # Show some examples
    print("Sample Patron Scenarios:")
    for i, patron in enumerate(generator.patrons[:10]):
        holds_count = len([h for h in generator.holds if h.patron_id == patron.patron_id])
        overdues_count = len([o for o in generator.overdues if o.patron_id == patron.patron_id])
        almost_count = len([a for a in generator.almost_overdues if a.patron_id == patron.patron_id])
        print(f"  {i+1}. {patron.first_name} {patron.last_name}: " +
              f"{holds_count} holds, {overdues_count} overdues, {almost_count} almost overdue, " +
              f"DeliveryOpt={patron.delivery_option_id}")

    print("\n✨ Data generation complete!")


if __name__ == "__main__":
    main()
