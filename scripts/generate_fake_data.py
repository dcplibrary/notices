#!/usr/bin/env python3
"""
Generate fake but realistic data for Polaris database sample CSV files.
Keeps real data for status/type reference tables.
Generates fake data for PII fields (names, addresses, emails, phones).
"""

import csv
import random
import re
from datetime import datetime, timedelta
from pathlib import Path
from faker import Faker
import hashlib

# Initialize Faker
fake = Faker()
Faker.seed(42)  # For reproducibility
random.seed(42)

# Base directory for sample data
SAMPLE_DATA_DIR = Path("polaris-databases/sample-data")

# Files that should keep their real data (status/type/reference tables)
KEEP_REAL_DATA = {
    "Polaris.Polaris.DeliveryOptions.csv",
    "Polaris.Polaris.NotificationStatuses.csv",
    "Polaris.Polaris.NotificationTypes.csv",
    "Polaris.Polaris.SysHoldStatuses.csv",
    "Polaris.Polaris.ItemStatuse.csv",
    "Polaris.Polaris.AddressTypes.csv",
    "Polaris.Polaris.AddressLabels.csv",
    "Polaris.Polaris.SA_DeliveryOptions.csv",
    "PolarisTransactions.Polaris.TransactionSubTypes.csv",
    "PolarisTransactions.Polaris.TransactionTypes.csv",
}

# Cache for consistent fake data generation
patron_cache = {}  # PatronID -> fake patron data
address_cache = {}  # AddressID -> fake address data
barcode_cache = {}  # Old barcode -> new barcode


def generate_phone():
    """Generate a fake US phone number."""
    return fake.numerify("270#######")  # Keep 270 area code for consistency


def generate_email(first_name, last_name):
    """Generate a fake email address based on name."""
    providers = ["gmail.com", "yahoo.com", "hotmail.com", "outlook.com", "icloud.com"]
    formats = [
        f"{first_name.lower()}.{last_name.lower()}",
        f"{first_name.lower()}{last_name.lower()}",
        f"{first_name.lower()}{random.randint(1, 999)}",
        f"{last_name.lower()}{first_name[0].lower()}",
    ]
    return f"{random.choice(formats)}@{random.choice(providers)}"


def generate_barcode(prefix="23307", length=14):
    """Generate a fake barcode with consistent format."""
    # Generate random digits to fill the rest
    remaining = length - len(prefix)
    return prefix + fake.numerify("#" * remaining)


def generate_password_hash():
    """Generate a fake bcrypt password hash."""
    # Generate a realistic looking bcrypt hash
    random_str = fake.password(length=20)
    return f"$2a$10${fake.lexify('?' * 53)}"


def generate_obfuscated_password():
    """Generate a fake obfuscated password."""
    # Generate a realistic looking base64-ish string
    return fake.lexify('?' * 22) + "=="


def get_fake_patron(patron_id):
    """Get or create fake patron data for a given patron ID."""
    if patron_id not in patron_cache:
        gender = random.choice([0, 1])  # 0=female, 1=male

        if gender == 0:
            first_name = fake.first_name_female()
        else:
            first_name = fake.first_name_male()

        last_name = fake.last_name()
        middle_name = fake.first_name() if random.random() > 0.3 else ""

        patron_cache[patron_id] = {
            "first_name": first_name.upper(),
            "last_name": last_name.upper(),
            "middle_name": middle_name.upper(),
            "full_name": f"{last_name.upper()}, {first_name.upper()}" + (f" {middle_name.upper()}" if middle_name else ""),
            "first_last_name": f"{first_name.upper()} {middle_name.upper() + ' ' if middle_name else ''}{last_name.upper()}",
            "email": generate_email(first_name, last_name) if random.random() > 0.2 else "",
            "phone1": generate_phone() if random.random() > 0.1 else "",
            "phone2": generate_phone() if random.random() > 0.8 else "",
            "phone3": generate_phone() if random.random() > 0.9 else "",
            "barcode": generate_barcode(),
            "password_hash": generate_password_hash(),
            "obfuscated_password": generate_obfuscated_password(),
        }

    return patron_cache[patron_id]


def get_fake_address(address_id):
    """Get or create fake address data for a given address ID."""
    if address_id not in address_cache:
        address_cache[address_id] = {
            "street_one": fake.street_address().upper(),
            "street_two": fake.secondary_address().upper() if random.random() > 0.7 else "",
            "street_three": "",
            "municipality": fake.city().upper() if random.random() > 0.5 else "",
        }

    return address_cache[address_id]


def anonymize_row(row, headers, filename):
    """Anonymize PII fields in a row based on the filename."""
    new_row = row.copy()

    # PatronRegistration table
    if "PatronRegistration" in filename:
        patron_id = row.get("PatronID", "")
        if patron_id:
            patron = get_fake_patron(patron_id)

            if "NameFirst" in headers:
                new_row["NameFirst"] = patron["first_name"]
            if "NameLast" in headers:
                new_row["NameLast"] = patron["last_name"]
            if "NameMiddle" in headers:
                new_row["NameMiddle"] = patron["middle_name"]
            if "PatronFullName" in headers:
                new_row["PatronFullName"] = patron["full_name"]
            if "PatronFirstLastName" in headers:
                new_row["PatronFirstLastName"] = patron["first_last_name"]
            if "EmailAddress" in headers:
                new_row["EmailAddress"] = patron["email"]
            if "AltEmailAddress" in headers:
                new_row["AltEmailAddress"] = generate_email(patron["first_name"], patron["last_name"]) if row.get("AltEmailAddress") else ""
            if "PhoneVoice1" in headers:
                new_row["PhoneVoice1"] = patron["phone1"]
            if "PhoneVoice2" in headers:
                new_row["PhoneVoice2"] = patron["phone2"]
            if "PhoneVoice3" in headers:
                new_row["PhoneVoice3"] = patron["phone3"]
            if "PhoneFAX" in headers:
                new_row["PhoneFAX"] = generate_phone() if row.get("PhoneFAX") else ""
            if "PasswordHash" in headers:
                new_row["PasswordHash"] = patron["password_hash"]
            if "ObfuscatedPassword" in headers:
                new_row["ObfuscatedPassword"] = patron["obfuscated_password"]
            if "Username" in headers and row.get("Username"):
                new_row["Username"] = patron["email"].split("@")[0] if patron["email"] else ""

    # Patrons table
    elif "Polaris.Polaris.Patrons.csv" == filename:
        patron_id = row.get("PatronID", "")
        if patron_id and "Barcode" in headers:
            patron = get_fake_patron(patron_id)
            new_row["Barcode"] = patron["barcode"]

    # Addresses table
    elif "Addresses.csv" in filename:
        address_id = row.get("AddressID", "")
        if address_id:
            address = get_fake_address(address_id)

            if "StreetOne" in headers:
                new_row["StreetOne"] = address["street_one"]
            if "StreetTwo" in headers:
                new_row["StreetTwo"] = address["street_two"]
            if "StreetThree" in headers:
                new_row["StreetThree"] = address["street_three"]
            if "MunicipalityName" in headers:
                new_row["MunicipalityName"] = address["municipality"]

    # PhoneNotices and other view files with patron data
    elif any(x in filename for x in ["PhoneNotices", "HoldNotices", "NotificationQueue",
                                      "NotificationHistory", "NotificationLogs", "OverdueNotices",
                                      "ManualBillNotices", "FineNotices", "CircReminders"]):
        # Handle first name / last name columns
        if "PatronID" in headers:
            patron_id = row.get("PatronID", "")
            if patron_id:
                patron = get_fake_patron(patron_id)

                # Different files have different column names for names
                for first_col in ["NameFirst", "ROSEMARY"]:  # Some files might have the actual name as column
                    if first_col in headers and row.get(first_col):
                        # This is likely a data value, extract from position
                        break

        # Handle various name field patterns
        for header_idx, header in enumerate(headers):
            value = row.get(header, "")

            # Email patterns
            if value and "@" in str(value) and "." in str(value):
                # Generate a fake email
                new_row[header] = generate_email(fake.first_name(), fake.last_name())

            # Phone patterns (10 digits, possibly with dashes or parentheses)
            elif value and re.match(r"^[\d\(\)\-\s]{10,}$", str(value)):
                new_row[header] = generate_phone()

            # Barcode patterns (starts with digits, 13-20 chars)
            elif value and re.match(r"^\d{13,20}$", str(value)):
                old_barcode = str(value)
                if old_barcode not in barcode_cache:
                    barcode_cache[old_barcode] = generate_barcode(length=len(old_barcode))
                new_row[header] = barcode_cache[old_barcode]

        # Specific column name handling
        if "DeliveryString" in headers and row.get("DeliveryString"):
            # If it's an email
            if "@" in row["DeliveryString"]:
                first = fake.first_name()
                last = fake.last_name()
                new_row["DeliveryString"] = generate_email(first, last)

    # Handle CSV files with unnamed columns (like PhoneNotices.csv)
    # These files have data embedded directly
    if filename == "PhoneNotices.csv":
        # This file has a complex format - let's handle it specially
        for key, value in new_row.items():
            if value and isinstance(value, str):
                # Replace emails
                if "@" in value:
                    new_row[key] = generate_email(fake.first_name(), fake.last_name())
                # Replace phone numbers (270 area code pattern)
                elif re.match(r"^270\d{7}$", value):
                    new_row[key] = generate_phone()
                # Replace barcodes
                elif re.match(r"^233070\d{8}$", value):
                    if value not in barcode_cache:
                        barcode_cache[value] = generate_barcode()
                    new_row[key] = barcode_cache[value]
                # Replace names (all caps, no digits)
                elif value.isupper() and re.match(r"^[A-Z\s\-\']+$", value) and len(value) > 2:
                    # Could be a name - replace with fake name
                    if " " not in value:  # Single word - could be last name
                        new_row[key] = fake.last_name().upper()
                    else:  # Multiple words
                        parts = value.split()
                        new_row[key] = " ".join([fake.first_name().upper() if i == 0 else fake.last_name().upper()
                                                for i in range(len(parts))])

    return new_row


def process_csv_file(filepath):
    """Process a single CSV file."""
    filename = filepath.name

    # Skip files that should keep real data
    if filename in KEEP_REAL_DATA:
        print(f"  ⏭️  Skipping (keeping real data): {filename}")
        return

    print(f"  🔄 Processing: {filename}")

    # Read the CSV file
    try:
        with open(filepath, 'r', encoding='utf-8-sig', newline='') as f:
            # Detect delimiter
            first_line = f.readline()
            f.seek(0)

            delimiter = '\t' if '\t' in first_line else ','

            reader = csv.DictReader(f, delimiter=delimiter)
            headers = reader.fieldnames

            if not headers:
                print(f"    ⚠️  No headers found, skipping")
                return

            rows = list(reader)
    except Exception as e:
        print(f"    ❌ Error reading file: {e}")
        return

    # Process each row
    anonymized_rows = []
    for row in rows:
        anonymized_row = anonymize_row(row, headers, filename)
        anonymized_rows.append(anonymized_row)

    # Write back to the file
    try:
        with open(filepath, 'w', encoding='utf-8', newline='') as f:
            writer = csv.DictWriter(f, fieldnames=headers, delimiter=delimiter)
            writer.writeheader()
            writer.writerows(anonymized_rows)

        print(f"    ✅ Anonymized {len(anonymized_rows)} rows")
    except Exception as e:
        print(f"    ❌ Error writing file: {e}")


def main():
    """Main function to process all CSV files."""
    print("🚀 Starting data anonymization process...")
    print(f"📁 Sample data directory: {SAMPLE_DATA_DIR}")
    print()

    # Get all CSV files
    csv_files = sorted(SAMPLE_DATA_DIR.glob("*.csv"))

    print(f"📊 Found {len(csv_files)} CSV files")
    print()

    # Process each file
    for csv_file in csv_files:
        process_csv_file(csv_file)

    print()
    print("✨ Data anonymization complete!")
    print(f"📈 Generated fake data for {len(patron_cache)} patrons")
    print(f"🏠 Generated fake data for {len(address_cache)} addresses")
    print(f"🔢 Generated {len(barcode_cache)} fake barcodes")


if __name__ == "__main__":
    main()
