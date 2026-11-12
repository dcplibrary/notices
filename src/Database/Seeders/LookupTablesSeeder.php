<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Dcplibrary\Notices\Models\DeliveryOption;
use Dcplibrary\Notices\Models\EmailReportType;
use Dcplibrary\Notices\Models\FineCode;
use Dcplibrary\Notices\Models\ItemStatus;
use Dcplibrary\Notices\Models\Language;
use Dcplibrary\Notices\Models\MaterialType;
use Dcplibrary\Notices\Models\NotificationStatus;
use Dcplibrary\Notices\Models\NotificationType;
use Dcplibrary\Notices\Models\PatronCode;
use Dcplibrary\Notices\Models\PhoneCarrier;
use Dcplibrary\Notices\Models\SysHoldStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds all static Polaris lookup tables used by the notices package.
 *
 * Populates the following tables with reference data that mirrors the
 * Polaris ILS configuration (IDs must match Polaris exactly):
 * - delivery_options         (Mail, Email, Voice, SMS IDs)
 * - notification_types       (Hold Pickup, 1st Overdue, Bill, etc.)
 * - notification_statuses    (Sent, Cancelled, Failed, etc.)
 * - sys_hold_statuses        (Held, In Transit, etc.)
 * - item_statuses
 * - material_types
 * - fine_codes
 * - languages
 * - patron_codes
 * - phone_carriers           (AT&T, T-Mobile, etc. SMS gateways)
 * - email_report_types       (Shoutbomb failure / Polaris summary categories)
 *
 * Run via `php artisan notices:seed` or `php artisan db:seed --class=LookupTablesSeeder`.
 */
class LookupTablesSeeder extends Seeder
{
    public function run(): void
    {
        $this->info('Seeding lookup tables...');

        $this->seedDeliveryOptions();
        $this->seedNotificationTypes();
        $this->seedNotificationStatuses();
        $this->seedSysHoldStatuses();
        $this->seedItemStatuses();
        $this->seedMaterialTypes();
        $this->seedFineCodes();
        $this->seedStatisticalCodes();
        $this->seedLanguages();
        $this->seedPatronCodes();
        $this->seedPhoneCarriers();
        $this->seedEmailReportTypes();
        $this->seedUiMessages();
        $this->seedStatusMessages();

        $this->info('Lookup tables seeded successfully!');
    }

    private function info(string $message): void
    {
        // When running in package tests, $this->command may be null.
        $this->command?->info($message);
    }

    private function seedDeliveryOptions(): void
    {
        $this->info('Seeding delivery_options...');

        $options = [
            ['id' => 1, 'delivery_option' => 'Mailing Address'],
            ['id' => 2, 'delivery_option' => 'Email Address'],
            ['id' => 3, 'delivery_option' => 'Phone 1'],
            ['id' => 4, 'delivery_option' => 'Phone 2'],
            ['id' => 5, 'delivery_option' => 'Phone 3'],
            ['id' => 6, 'delivery_option' => 'FAX'],
            ['id' => 7, 'delivery_option' => 'EDI'],
            ['id' => 8, 'delivery_option' => 'TXT Messaging'],
        ];

        foreach ($options as $option) {
            DeliveryOption::updateOrCreate(['id' => $option['id']], $option);
        }

        $this->info('✓ Seeded ' . count($options) . ' delivery options');
    }

    private function seedNotificationTypes(): void
    {
        $this->info('Seeding notification_types...');

        $types = [
            ['id' => 0, 'description' => 'Combined'],
            ['id' => 1, 'description' => '1st Overdue'],
            ['id' => 2, 'description' => 'Hold'],
            ['id' => 3, 'description' => 'Cancel'],
            ['id' => 4, 'description' => 'Recall'],
            ['id' => 5, 'description' => 'All'],
            ['id' => 6, 'description' => 'Route'],
            ['id' => 7, 'description' => 'Almost overdue/Auto-renew reminder'],
            ['id' => 8, 'description' => 'Fine'],
            ['id' => 9, 'description' => 'Inactive Reminder'],
            ['id' => 10, 'description' => 'Expiration Reminder'],
            ['id' => 11, 'description' => 'Bill'],
            ['id' => 12, 'description' => '2nd Overdue'],
            ['id' => 13, 'description' => '3rd Overdue'],
            ['id' => 14, 'description' => 'Serial Claim'],
            ['id' => 15, 'description' => 'Polaris Fusion Access Request'],
            ['id' => 16, 'description' => 'Course Reserves'],
            ['id' => 17, 'description' => 'Borrow-By-Mail Failure'],
            ['id' => 18, 'description' => '2nd Hold'],
            ['id' => 19, 'description' => 'Missing Part'],
            ['id' => 20, 'description' => 'Manual Bill'],
            ['id' => 21, 'description' => '2nd Fine Notice'],
        ];

        foreach ($types as $type) {
            NotificationType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info('✓ Seeded ' . count($types) . ' notification types');
    }

    private function seedNotificationStatuses(): void
    {
        $this->info('Seeding notification_statuses...');

        $statuses = [
            ['id' => 1, 'description' => 'Call completed - Voice'],
            ['id' => 2, 'description' => 'Call completed - Answering machine'],
            ['id' => 3, 'description' => 'Delivered (SMS)'],
            ['id' => 4, 'description' => 'Failed - Invalid Phone'],
            ['id' => 5, 'description' => 'Failed - No Answer'],
            ['id' => 6, 'description' => 'Failed - Invalid Email'],
            ['id' => 7, 'description' => 'Delivered (Email)'],
            ['id' => 8, 'description' => 'Failed - Disconnected'],
            ['id' => 9, 'description' => 'Delivered (Voice)'],
            ['id' => 10, 'description' => 'Failed - Opted Out'],
            ['id' => 11, 'description' => 'Call failed - Undetermined error'],
            ['id' => 12, 'description' => 'Email Completed'],
            ['id' => 13, 'description' => 'Email Failed - Invalid address'],
            ['id' => 14, 'description' => 'Email Failed'],
            ['id' => 15, 'description' => 'Mail Printed'],
            ['id' => 16, 'description' => 'Sent'],
            ['id' => 17, 'description' => 'Email address missing for LX Starter'],
        ];

        foreach ($statuses as $status) {
            NotificationStatus::updateOrCreate(['id' => $status['id']], $status);
        }

        $this->info('✓ Seeded ' . count($statuses) . ' notification statuses');
    }

    private function seedSysHoldStatuses(): void
    {
        $this->info('Seeding sys_hold_statuses...');

        $statuses = [
            ['id' => 1, 'description' => 'Active', 'name' => 'Active'],
            ['id' => 2, 'description' => 'Held', 'name' => 'Held'],
            ['id' => 3, 'description' => 'Shipped', 'name' => 'Shipped'],
            ['id' => 4, 'description' => 'Cancelled', 'name' => 'Cancelled'],
            ['id' => 5, 'description' => 'Expired', 'name' => 'Expired'],
            ['id' => 6, 'description' => 'Not Supplied', 'name' => 'Not Supplied'],
            ['id' => 7, 'description' => 'Pending', 'name' => 'Pending'],
            ['id' => 8, 'description' => 'Out', 'name' => 'Out'],
            ['id' => 9, 'description' => 'Transferred', 'name' => 'Transferred'],
            ['id' => 10, 'description' => 'Unclaimed', 'name' => 'Unclaimed'],
            ['id' => 11, 'description' => 'Inactive', 'name' => 'Inactive'],
        ];

        foreach ($statuses as $status) {
            SysHoldStatus::updateOrCreate(['id' => $status['id']], $status);
        }

        $this->info('✓ Seeded ' . count($statuses) . ' sys hold statuses');
    }

    private function seedItemStatuses(): void
    {
        $this->info('Seeding item_statuses...');

        // NOTE: item_statuses.id is a non-auto-increment primary key that should
        // mirror Polaris item status IDs. We seed with explicit IDs so this
        // works on MySQL (no default value error on id) and can be aligned to
        // real Polaris IDs in production.
        $statuses = [
            ['id' => 1, 'description' => 'Checked Out', 'name' => 'In', 'banner_text' => 'IN'],
            ['id' => 2, 'description' => 'Checked In', 'name' => 'Out', 'banner_text' => 'OUT'],
            ['id' => 3, 'description' => 'In Transit', 'name' => 'Transit', 'banner_text' => 'TRANSIT'],
            ['id' => 4, 'description' => 'Held', 'name' => 'Held', 'banner_text' => 'HELD'],
            ['id' => 5, 'description' => 'Missing', 'name' => 'Missing', 'banner_text' => 'MISSING'],
            ['id' => 6, 'description' => 'Lost', 'name' => 'Lost', 'banner_text' => 'LOST'],
            ['id' => 7, 'description' => 'Claims Returned', 'name' => 'Claims Returned', 'banner_text' => 'CLAIMS RET'],
            ['id' => 8, 'description' => 'Damaged', 'name' => 'Damaged', 'banner_text' => 'DAMAGED'],
            ['id' => 9, 'description' => 'On Order', 'name' => 'On Order', 'banner_text' => 'ON ORDER'],
            ['id' => 10, 'description' => 'In Repair', 'name' => 'In Repair', 'banner_text' => 'IN REPAIR'],
            ['id' => 11, 'description' => 'Bindery', 'name' => 'Bindery', 'banner_text' => 'BINDERY'],
            ['id' => 12, 'description' => 'Cataloging', 'name' => 'Cataloging', 'banner_text' => 'CATALOGING'],
            ['id' => 13, 'description' => 'In Process', 'name' => 'In Process', 'banner_text' => 'IN PROCESS'],
            ['id' => 14, 'description' => 'Non-circulating', 'name' => 'Non-circ', 'banner_text' => 'NON-CIRC'],
            ['id' => 15, 'description' => 'Withdrawn', 'name' => 'Withdrawn', 'banner_text' => 'WITHDRAWN'],
            ['id' => 16, 'description' => 'Ordered', 'name' => 'Ordered', 'banner_text' => 'ORDERED'],
            ['id' => 17, 'description' => 'Received', 'name' => 'Received', 'banner_text' => 'RECEIVED'],
            ['id' => 18, 'description' => 'Ready for Pickup', 'name' => 'Ready', 'banner_text' => 'READY'],
            ['id' => 19, 'description' => 'Available', 'name' => 'Available', 'banner_text' => 'AVAILABLE'],
            ['id' => 20, 'description' => 'Unavailable', 'name' => 'Unavailable', 'banner_text' => 'UNAVAILABLE'],
            ['id' => 21, 'description' => 'On Display', 'name' => 'On Display', 'banner_text' => 'DISPLAY'],
            ['id' => 22, 'description' => 'Reserved', 'name' => 'Reserved', 'banner_text' => 'RESERVED'],
            ['id' => 23, 'description' => 'Incomplete', 'name' => 'Incomplete', 'banner_text' => 'INCOMPLETE'],
        ];

        foreach ($statuses as $status) {
            ItemStatus::updateOrCreate(['id' => $status['id']], $status);
        }

        $this->info('✓ Seeded ' . count($statuses) . ' item statuses');
    }

    private function seedMaterialTypes(): void
    {
        $this->info('Seeding material_types...');

        $types = [
            ['id' => 1, 'description' => 'Books', 'minimum_age' => 0],
            ['id' => 3, 'description' => 'Bulk Loan', 'minimum_age' => 0],
            ['id' => 4, 'description' => 'Equipment', 'minimum_age' => 0],
            ['id' => 7, 'description' => 'ILL', 'minimum_age' => 0],
            ['id' => 12, 'description' => 'Juvenile Audio', 'minimum_age' => 0],
            ['id' => 13, 'description' => 'Juvenile DVD/Video Book', 'minimum_age' => 0],
            ['id' => 14, 'description' => 'Juvenile DVD/Video', 'minimum_age' => 0],
            ['id' => 15, 'description' => 'Kits', 'minimum_age' => 0],
            ['id' => 16, 'description' => 'Magazines', 'minimum_age' => 0],
            ['id' => 20, 'description' => 'Reference', 'minimum_age' => 0],
            ['id' => 21, 'description' => 'CD', 'minimum_age' => 0],
            ['id' => 22, 'description' => 'CD - Restricted', 'minimum_age' => 0],
            ['id' => 24, 'description' => 'DVD/Video', 'minimum_age' => 0],
            ['id' => 25, 'description' => 'DVD/Video Book', 'minimum_age' => 0],
            ['id' => 26, 'description' => 'DVD/Video - Restricted', 'minimum_age' => 0],
            ['id' => 27, 'description' => 'KR DVD', 'minimum_age' => 0],
            ['id' => 30, 'description' => 'Bestseller Xpress', 'minimum_age' => 0],
            ['id' => 34, 'description' => 'Blu-ray Disc', 'minimum_age' => 0],
            ['id' => 35, 'description' => 'Blu-ray Disc - Restricted', 'minimum_age' => 0],
            ['id' => 36, 'description' => 'Juvenile Blu-ray Disc', 'minimum_age' => 0],
            ['id' => 37, 'description' => 'Video Game - E', 'minimum_age' => 0],
            ['id' => 38, 'description' => 'Video Game - Restricted', 'minimum_age' => 0],
            ['id' => 39, 'description' => 'Video Game - Teen', 'minimum_age' => 0],
            ['id' => 41, 'description' => 'Museum Pass', 'minimum_age' => 0],
            ['id' => 43, 'description' => 'Streaming Devices', 'minimum_age' => 0],
            ['id' => 44, 'description' => 'Mobile Hotspot Devices', 'minimum_age' => 0],
            ['id' => 45, 'description' => 'Audiobook CD', 'minimum_age' => 0],
            ['id' => 47, 'description' => 'Edge Ice Pass', 'minimum_age' => 0],
            ['id' => 48, 'description' => 'Kiosk Laptop', 'minimum_age' => 0],
            ['id' => 49, 'description' => 'Juvenile Book', 'minimum_age' => 0],
            ['id' => 50, 'description' => 'Juvenile Magazine', 'minimum_age' => 0],
            ['id' => 51, 'description' => 'Kits - Outreach', 'minimum_age' => 0],
            ['id' => 52, 'description' => 'eBooks', 'minimum_age' => 0],
            ['id' => 53, 'description' => 'eAudioBooks', 'minimum_age' => 0],
        ];

        foreach ($types as $type) {
            MaterialType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info('✓ Seeded ' . count($types) . ' material types');
    }

    private function seedFineCodes(): void
    {
        $this->info('Seeding fine_codes...');

        $codes = [
            ['id' => 1, 'description' => 'Overdue Fine'],
            ['id' => 2, 'description' => 'Lost Item Replacement'],
            ['id' => 3, 'description' => 'Damaged Item Fee'],
            ['id' => 4, 'description' => 'Processing Fee'],
            ['id' => 5, 'description' => 'Late Fee'],
            ['id' => 6, 'description' => 'Non-Return Fee'],
            ['id' => 7, 'description' => 'Lost Card Replacement'],
            ['id' => 8, 'description' => 'Printing Fee'],
            ['id' => 9, 'description' => 'Photocopy Fee'],
            ['id' => 10, 'description' => 'Technology Fee'],
            ['id' => 11, 'description' => 'ILL Fee'],
            ['id' => 12, 'description' => 'Rental Fee'],
            ['id' => 13, 'description' => 'Manual Fee'],
            ['id' => 14, 'description' => 'Computer Fee'],
            ['id' => 15, 'description' => 'Other Fee'],
        ];

        foreach ($codes as $code) {
            FineCode::updateOrCreate(['id' => $code['id']], $code);
        }

        $this->info('✓ Seeded ' . count($codes) . ' fine codes');
    }

    private function seedStatisticalCodes(): void
    {
        $this->info('Seeding statistical_codes...');

        // Sample statistical codes - these would be customized per library
        $codes = [
            ['statistical_code_id' => 1, 'organization_id' => 3, 'description' => 'Adult'],
            ['statistical_code_id' => 2, 'organization_id' => 3, 'description' => 'Teen'],
            ['statistical_code_id' => 3, 'organization_id' => 3, 'description' => 'Child'],
            ['statistical_code_id' => 4, 'organization_id' => 3, 'description' => 'Senior'],
            ['statistical_code_id' => 5, 'organization_id' => 3, 'description' => 'Student'],
            ['statistical_code_id' => 6, 'organization_id' => 3, 'description' => 'Educator'],
            ['statistical_code_id' => 7, 'organization_id' => 3, 'description' => 'Staff'],
            ['statistical_code_id' => 8, 'organization_id' => 3, 'description' => 'Homebound'],
            ['statistical_code_id' => 9, 'organization_id' => 3, 'description' => 'Business'],
            ['statistical_code_id' => 10, 'organization_id' => 3, 'description' => 'Government'],
        ];

        // Use DB facade for composite primary key table
        foreach ($codes as $code) {
            $existing = DB::table('statistical_codes')
                ->where('statistical_code_id', $code['statistical_code_id'])
                ->where('organization_id', $code['organization_id'])
                ->first()
            ;

            if ($existing) {
                DB::table('statistical_codes')
                    ->where('statistical_code_id', $code['statistical_code_id'])
                    ->where('organization_id', $code['organization_id'])
                    ->update([
                        'description' => $code['description'],
                        'updated_at'  => now(),
                    ])
                ;
            } else {
                DB::table('statistical_codes')->insert(array_merge($code, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $this->info('✓ Seeded ' . count($codes) . ' statistical codes');
    }

    private function seedLanguages(): void
    {
        $this->info('Seeding languages...');

        $languages = [
            ['id' => 1, 'language_desc' => 'English', 'ncip_value' => 'eng', 'admin_language_id' => 1033],
            ['id' => 2, 'language_desc' => 'Spanish', 'ncip_value' => 'spa', 'admin_language_id' => 3082],
            ['id' => 3, 'language_desc' => 'German', 'ncip_value' => 'ger', 'admin_language_id' => 1031],
            ['id' => 4, 'language_desc' => 'French', 'ncip_value' => 'fre', 'admin_language_id' => 3084],
            ['id' => 5, 'language_desc' => 'Italian', 'ncip_value' => 'ita', 'admin_language_id' => null],
            ['id' => 6, 'language_desc' => 'Hebrew', 'ncip_value' => 'heb', 'admin_language_id' => null],
            ['id' => 7, 'language_desc' => 'Hungarian', 'ncip_value' => 'hun', 'admin_language_id' => null],
            ['id' => 8, 'language_desc' => 'Chinese', 'ncip_value' => 'chi', 'admin_language_id' => 2052],
            ['id' => 9, 'language_desc' => 'Polish', 'ncip_value' => 'pol', 'admin_language_id' => 1045],
            ['id' => 10, 'language_desc' => 'Korean', 'ncip_value' => 'kor', 'admin_language_id' => 1042],
            ['id' => 11, 'language_desc' => 'Japanese', 'ncip_value' => 'jpn', 'admin_language_id' => null],
            ['id' => 12, 'language_desc' => 'Arabic', 'ncip_value' => 'ara', 'admin_language_id' => 12289],
            ['id' => 13, 'language_desc' => 'Greek', 'ncip_value' => 'gre', 'admin_language_id' => null],
            ['id' => 14, 'language_desc' => 'Romanian', 'ncip_value' => 'rum', 'admin_language_id' => null],
            ['id' => 15, 'language_desc' => 'Portuguese', 'ncip_value' => 'por', 'admin_language_id' => null],
            ['id' => 16, 'language_desc' => 'Vietnamese', 'ncip_value' => 'vie', 'admin_language_id' => 1066],
            ['id' => 17, 'language_desc' => 'Russian', 'ncip_value' => 'rus', 'admin_language_id' => 1049],
            ['id' => 18, 'language_desc' => 'Albanian', 'ncip_value' => 'alb', 'admin_language_id' => null],
            ['id' => 19, 'language_desc' => 'Armenian', 'ncip_value' => 'arm', 'admin_language_id' => null],
            ['id' => 20, 'language_desc' => 'Swedish', 'ncip_value' => 'swe', 'admin_language_id' => null],
            ['id' => 21, 'language_desc' => 'Turkish', 'ncip_value' => 'tur', 'admin_language_id' => null],
            ['id' => 22, 'language_desc' => 'Slovenian', 'ncip_value' => 'slv', 'admin_language_id' => null],
            ['id' => 23, 'language_desc' => 'Ukrainian', 'ncip_value' => 'ukr', 'admin_language_id' => null],
            ['id' => 24, 'language_desc' => 'Hindi', 'ncip_value' => 'hin', 'admin_language_id' => 1081],
            ['id' => 25, 'language_desc' => 'Farsi', 'ncip_value' => 'fas', 'admin_language_id' => 1065],
            ['id' => 26, 'language_desc' => 'Thai', 'ncip_value' => 'tha', 'admin_language_id' => null],
            ['id' => 27, 'language_desc' => 'Indonesian', 'ncip_value' => 'ind', 'admin_language_id' => null],
            ['id' => 28, 'language_desc' => 'Malay', 'ncip_value' => 'msa', 'admin_language_id' => null],
            ['id' => 29, 'language_desc' => 'Tagalog', 'ncip_value' => 'tgl', 'admin_language_id' => null],
            ['id' => 30, 'language_desc' => 'Bengali', 'ncip_value' => 'ben', 'admin_language_id' => null],
            ['id' => 31, 'language_desc' => 'Urdu', 'ncip_value' => 'urd', 'admin_language_id' => null],
            ['id' => 32, 'language_desc' => 'Dutch', 'ncip_value' => 'dut', 'admin_language_id' => null],
            ['id' => 33, 'language_desc' => 'Danish', 'ncip_value' => 'dan', 'admin_language_id' => null],
            ['id' => 34, 'language_desc' => 'Norwegian', 'ncip_value' => 'nor', 'admin_language_id' => null],
            ['id' => 35, 'language_desc' => 'Finnish', 'ncip_value' => 'fin', 'admin_language_id' => null],
            ['id' => 36, 'language_desc' => 'Czech', 'ncip_value' => 'cze', 'admin_language_id' => null],
            ['id' => 37, 'language_desc' => 'Slovak', 'ncip_value' => 'slk', 'admin_language_id' => null],
            ['id' => 38, 'language_desc' => 'Bulgarian', 'ncip_value' => 'bul', 'admin_language_id' => null],
            ['id' => 39, 'language_desc' => 'Croatian', 'ncip_value' => 'hrv', 'admin_language_id' => null],
            ['id' => 40, 'language_desc' => 'Serbian', 'ncip_value' => 'srp', 'admin_language_id' => null],
            ['id' => 41, 'language_desc' => 'Latvian', 'ncip_value' => 'lav', 'admin_language_id' => null],
            ['id' => 42, 'language_desc' => 'Lithuanian', 'ncip_value' => 'lit', 'admin_language_id' => null],
            ['id' => 43, 'language_desc' => 'Estonian', 'ncip_value' => 'est', 'admin_language_id' => null],
            ['id' => 44, 'language_desc' => 'Other', 'ncip_value' => 'oth', 'admin_language_id' => null],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['id' => $language['id']], $language);
        }

        $this->info('✓ Seeded ' . count($languages) . ' languages');
    }

    private function seedPatronCodes(): void
    {
        $this->info('Seeding patron_codes...');

        $codes = [
            ['id' => 1, 'description' => 'Adult Courtesy'],
            ['id' => 2, 'description' => 'Adult Paid'],
            ['id' => 3, 'description' => 'Adult'],
            ['id' => 4, 'description' => 'Bulk Loan Institutions'],
            ['id' => 5, 'description' => 'Business Use Only'],
            ['id' => 7, 'description' => 'Homebound'],
            ['id' => 8, 'description' => 'ILL'],
            ['id' => 9, 'description' => 'Juvenile Courtesy'],
            ['id' => 11, 'description' => 'Juvenile Paid'],
            ['id' => 13, 'description' => 'Juvenile'],
            ['id' => 15, 'description' => 'Library Board'],
            ['id' => 16, 'description' => 'Outreach Institutions'],
            ['id' => 17, 'description' => 'Staff'],
            ['id' => 18, 'description' => 'Teen Courtesy'],
            ['id' => 22, 'description' => 'Limited Access Courtesy'],
            ['id' => 23, 'description' => 'Teen Paid'],
            ['id' => 27, 'description' => 'Teen'],
            ['id' => 31, 'description' => 'Limited Access Paid'],
            ['id' => 37, 'description' => 'Juvenile Expanded Movie'],
            ['id' => 38, 'description' => 'Teen Pass'],
            ['id' => 39, 'description' => 'Juvenile Courtesy Expanded Movie'],
            ['id' => 40, 'description' => 'Limited Access'],
            ['id' => 41, 'description' => 'Teacher'],
        ];

        foreach ($codes as $code) {
            PatronCode::updateOrCreate(['id' => $code['id']], $code);
        }

        $this->info('✓ Seeded ' . count($codes) . ' patron codes');
    }

    private function seedPhoneCarriers(): void
    {
        $this->info('Seeding phone_carriers...');

        // Common US phone carriers with SMS gateways
        // IMPORTANT: phone_carriers.id is UNSIGNED in MySQL, so we cannot use
        // negative Polaris sentinel values (-2, -1). We map them to high
        // positive IDs that won't conflict with real carrier IDs.
        $carriers = [
            ['id' => 9001, 'name' => 'Unknown/No Carrier', 'sms_gateway' => null, 'active' => true],
            ['id' => 9002, 'name' => 'Not Specified', 'sms_gateway' => null, 'active' => true],
            ['id' => 1, 'name' => 'AT&T', 'sms_gateway' => '@txt.att.net', 'active' => true],
            ['id' => 2, 'name' => 'Verizon', 'sms_gateway' => '@vtext.com', 'active' => true],
            ['id' => 3, 'name' => 'T-Mobile', 'sms_gateway' => '@tmomail.net', 'active' => true],
            ['id' => 4, 'name' => 'Sprint', 'sms_gateway' => '@messaging.sprintpcs.com', 'active' => true],
            ['id' => 5, 'name' => 'Metro PCS', 'sms_gateway' => '@mymetropcs.com', 'active' => true],
            ['id' => 6, 'name' => 'Boost Mobile', 'sms_gateway' => '@sms.myboostmobile.com', 'active' => true],
            ['id' => 7, 'name' => 'Cricket', 'sms_gateway' => '@sms.cricketwireless.net', 'active' => true],
            ['id' => 8, 'name' => 'US Cellular', 'sms_gateway' => '@email.uscc.net', 'active' => true],
            ['id' => 9, 'name' => 'Virgin Mobile', 'sms_gateway' => '@vmobl.com', 'active' => true],
            ['id' => 10, 'name' => 'Tracfone', 'sms_gateway' => '@mmst5.tracfone.com', 'active' => true],
            ['id' => 11, 'name' => 'Consumer Cellular', 'sms_gateway' => '@mailmymobile.net', 'active' => true],
            ['id' => 12, 'name' => 'Google Fi', 'sms_gateway' => '@msg.fi.google.com', 'active' => true],
            ['id' => 13, 'name' => 'Straight Talk', 'sms_gateway' => '@vtext.com', 'active' => true],
            ['id' => 14, 'name' => 'Xfinity Mobile', 'sms_gateway' => '@vtext.com', 'active' => true],
            ['id' => 15, 'name' => 'Republic Wireless', 'sms_gateway' => '@text.republicwireless.com', 'active' => true],
        ];

        foreach ($carriers as $carrier) {
            PhoneCarrier::updateOrCreate(['id' => $carrier['id']], $carrier);
        }

        $this->info('✓ Seeded ' . count($carriers) . ' phone carriers');
    }

    private function seedUiMessages(): void
    {
        $this->info('Seeding ui_messages...');

        $defaults = config('notices.ui_messages', []);

        foreach ($defaults as $key => $message) {
            \Dcplibrary\Notices\Models\UiMessage::updateOrCreate(
                ['key' => $key],
                [
                    'message' => $message,
                    'label'   => ucwords(str_replace('_', ' ', $key)),
                ]
            );
        }

        $this->info('✓ Seeded ' . count($defaults) . ' UI messages');
    }

    private function seedStatusMessages(): void
    {
        $this->info('Seeding status_messages...');

        $messages = [
            // ===== DELIVERED MESSAGES (4) =====
            [
                'code'             => 'delivered_email',
                'status'           => 'delivered',
                'label'            => 'Email Delivered',
                'message'          => '<p>This notification was successfully delivered to the patron\'s email address.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 1,
            ],
            [
                'code'             => 'delivered_voice',
                'status'           => 'delivered',
                'label'            => 'Voice Call Delivered',
                'message'          => '<p>This notification was successfully delivered via phone call.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 2,
            ],
            [
                'code'             => 'delivered_sms',
                'status'           => 'delivered',
                'label'            => 'Text Message Delivered',
                'message'          => '<p>This notification was successfully delivered via text message.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 3,
            ],
            [
                'code'             => 'delivered_mail',
                'status'           => 'delivered',
                'label'            => 'Mail Printed',
                'message'          => '<p>This notification was successfully printed for mailing.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 4,
            ],

            // ===== PENDING MESSAGES (4) =====
            [
                'code'             => 'pending_email',
                'status'           => 'pending',
                'label'            => 'Email Pending',
                'message'          => '<p>This notification is scheduled for delivery today.  Confirmation of successful delivery may take up to 24 hrs.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 1,
            ],
            [
                'code'             => 'pending_voice',
                'status'           => 'pending',
                'label'            => 'Voice Call Pending',
                'message'          => '<p>This notification is scheduled for delivery today.  Confirmation of successful delivery may take up to 24 hrs.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 2,
            ],
            [
                'code'             => 'pending_sms',
                'status'           => 'pending',
                'label'            => 'Text Message Pending',
                'message'          => '<p>This notification is scheduled for delivery today.  Confirmation of successful delivery may take up to 24 hrs.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 3,
            ],
            [
                'code'             => 'pending_mail',
                'status'           => 'pending',
                'label'            => 'Mail Pending',
                'message'          => '<p>This notification is being processed for mail delivery. Please check back later.</p>',
                'action'           => null,
                'source'           => null,
                'is_contact_issue' => false,
                'sort_order'       => 4,
            ],

            // ===== FAILED MESSAGES (10) =====
            [
                'code'             => 'opted_out',
                'status'           => 'failed',
                'label'            => 'Opted Out',
                'message'          => '<p>Patron opted out of text messaging.</p>',
                'action'           => '<p>Ask the patron to text <strong>START</strong> to <strong>1-866-371-1276</strong> or select a different notification preference.</p>',
                'source'           => 'shoutbomb',
                'is_contact_issue' => true,
                'sort_order'       => 1,
            ],
            [
                'code'             => 'phone_unreachable',
                'status'           => 'failed',
                'label'            => 'Phone Number Unreachable',
                'message'          => '<p>Patron\'s phone number is not accepting text notifications.</p>',
                'action'           => '<p>Ask the patron to text <strong>HELP</strong> to <strong>1-866-371-1276</strong> to verify they can receive text notifications. The patron should add <strong>1-866-371-1276</strong> to their allowed contacts list. If no response is received, select a different notification preference.</p>',
                'source'           => 'shoutbomb',
                'is_contact_issue' => true,
                'sort_order'       => 2,
            ],
            [
                'code'             => 'call_not_completed',
                'status'           => 'failed',
                'label'            => 'Call Could Not Be Completed',
                'message'          => '<p>Patron\'s phone service may be disconnected.</p>',
                'action'           => '<p>Ask the patron for their current phone number. If it matches what is on file, ask them to call <strong>270-684-9100</strong> and add <strong>270-684-9100</strong> to their allowed contacts list. If the issue persists, select a different notification preference.</p>',
                'source'           => 'shoutbomb',
                'is_contact_issue' => true,
                'sort_order'       => 3,
            ],
            [
                'code'             => 'voice_failure',
                'status'           => 'failed',
                'label'            => 'Voice Delivery Failure',
                'message'          => '<p>Phone notification was not delivered.</p>',
                'action'           => '<p>There may have been a temporary disruption of service. If the problem persists, verify the patron\'s phone number or select a different notification preference.</p>',
                'source'           => 'shoutbomb',
                'is_contact_issue' => true,
                'sort_order'       => 4,
            ],
            [
                'code'             => 'email_invalid_address',
                'status'           => 'failed',
                'label'            => 'Invalid Email Address',
                'message'          => '<p>Patron\'s email address is invalid.</p>',
                'action'           => '<p>Ask the patron for a new email address or select a different notification preference.</p>',
                'source'           => 'notification_log',
                'is_contact_issue' => true,
                'sort_order'       => 5,
            ],
            [
                'code'             => 'email_delivery_failure',
                'status'           => 'failed',
                'label'            => 'Email Delivery Failure',
                'message'          => '<p>Email delivery failed.</p>',
                'action'           => '<p>There may have been a temporary problem with the patron\'s email service. Ask the patron to add <strong>no-reply@dcplibrary.org</strong> to their contacts or allowed list. If the problem persists, request a new email address or select a different notification preference.</p>',
                'source'           => 'notification_log',
                'is_contact_issue' => true,
                'sort_order'       => 6,
            ],
            [
                'code'             => 'null_preference',
                'status'           => 'failed',
                'label'            => 'No Notification Preference',
                'message'          => '<p>Patron has no notification preference.</p>',
                'action'           => '<p>Select a temporary notification preference for the patron until they can update their preferred method.</p>',
                'source'           => 'system',
                'is_contact_issue' => true,
                'sort_order'       => 7,
            ],
            [
                'code'             => 'mismatched_preference',
                'status'           => 'failed',
                'label'            => 'Mismatched Preference',
                'message'          => '<p>Phone number is associated with both phone and text notifications.</p>',
                'action'           => '<p>This phone number is used by multiple patrons, some using phone notifications and some using text messaging. Update any accounts using this phone number to use the same notification preference as the account that was updated last.</p>',
                'source'           => 'shoutbomb',
                'is_contact_issue' => true,
                'sort_order'       => 8,
            ],
            [
                'code'             => 'polaris_export_failure',
                'status'           => 'failed',
                'label'            => 'Polaris Export Failure',
                'message'          => '<p>Polaris/LX Starter communication failure.</p>',
                'action'           => '<p>Polaris/LX Starter failed to send this notification. This may be a temporary issue. If you have access to <a href="https://davi.na3.iiivega.com/lxadmin" target="_blank">LX Starter</a>, verify that the patron is receiving notifications. If the patron\'s latest notifications do not appear in LX Starter, contact tech support. You may also change the patron\'s notification preference.</p>',
                'source'           => 'notification_log',
                'is_contact_issue' => false,
                'sort_order'       => 9,
            ],
            [
                'code'             => 'lx_starter_failure',
                'status'           => 'failed',
                'label'            => 'LX Starter Error',
                'message'          => '<p>Polaris/LX Starter failed to send email.</p>',
                'action'           => '<p>Polaris/LX Starter failed to send this notification. This may be a temporary issue. If you have access to <a href="https://davi.na3.iiivega.com/lxadmin" target="_blank">LX Starter</a>, verify that the patron is receiving notifications. If the patron\'s latest notifications do not appear in LX Starter, contact tech support. You may also change the patron\'s notification preference.</p>',
                'source'           => 'notification_log',
                'is_contact_issue' => false,
                'sort_order'       => 10,
            ],
        ];

        foreach ($messages as $msg) {
            \Dcplibrary\Notices\Models\StatusMessage::updateOrCreate(
                ['code' => $msg['code']],
                $msg
            );
        }

        $this->info('✓ Seeded ' . count($messages) . ' status messages');
    }

    private function seedEmailReportTypes(): void
    {
        $this->info('Seeding email_report_types...');

        $types = [
            // Shoutbomb failure reports
            [
                'code'          => 'shoutbomb_voice_failure',
                'label'         => 'Voice Delivery Failures',
                'category'      => 'shoutbomb',
                'description'   => 'Voice notices that were not delivered',
                'display_order' => 10,
            ],
            [
                'code'          => 'shoutbomb_invalid_phone',
                'label'         => 'Invalid Phone Numbers',
                'category'      => 'shoutbomb',
                'description'   => 'Invalid patron phone numbers (opted out or invalid)',
                'display_order' => 20,
            ],
            [
                'code'          => 'shoutbomb_voice_system_error',
                'label'         => 'Voice System Errors',
                'category'      => 'shoutbomb',
                'description'   => 'Calls that generated system errors',
                'display_order' => 30,
            ],
            [
                'code'          => 'shoutbomb_mismatched_preference',
                'label'         => 'Mismatched Patron Preference',
                'category'      => 'shoutbomb',
                'description'   => 'Conflicting delivery preferences across barcodes sharing the same phone number',
                'display_order' => 35,
            ],

            // Polaris reports
            [
                'code'          => 'polaris_summary_report',
                'label'         => 'Polaris Summary Report',
                'category'      => 'polaris',
                'description'   => 'Daily email and SMS delivery statistics from Polaris',
                'display_order' => 40,
            ],

            // Other notifications
            [
                'code'          => 'notice_files',
                'label'         => 'Notice Files Status',
                'category'      => 'other',
                'description'   => 'Status notifications about notice file uploads',
                'display_order' => 90,
            ],
            [
                'code'          => 'other',
                'label'         => 'Other',
                'category'      => 'other',
                'description'   => 'Unclassified email reports',
                'display_order' => 100,
            ],
        ];

        foreach ($types as $type) {
            EmailReportType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        $this->info('✓ Seeded ' . count($types) . ' email report types');
    }
}
