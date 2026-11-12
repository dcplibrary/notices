<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $messages = [
            // Delivered messages
            'user_portal_delivered_email' => '<p>This email notification was successfully delivered to the patron\'s email address.</p>',
            'user_portal_delivered_sms'   => '<p>This text message was successfully delivered to the patron\'s phone.</p>',
            'user_portal_delivered_voice' => '<p>This voice notification was successfully delivered to the patron\'s phone.</p>',

            // Pending messages
            'user_portal_pending_email' => '<p>This email notification is being processed and will be delivered shortly. Please check back later for an update.</p>',
            'user_portal_pending_sms'   => '<p>This text message is being processed. Delivery typically occurs within a few minutes.</p>',
            'user_portal_pending_voice' => '<p>This voice notification is queued for delivery. The call will be attempted shortly.</p>',

            // Failed email messages
            'user_portal_failed_email'         => '<p>This email notification could not be delivered.</p><p><strong>What to do:</strong></p><ul><li>Verify the patron\'s email address is correct in Polaris</li><li>Check if the mailbox is full or accepting messages</li><li>Consider switching to an alternative notification method</li></ul>',
            'user_portal_failed_email_bounce'  => '<p>This email bounced back as undeliverable.</p><p><strong>Common causes:</strong></p><ul><li>The email address doesn\'t exist</li><li>The mailbox is full</li><li>The email server rejected the message</li></ul><p><strong>Action:</strong> Update the patron\'s email address in Polaris or switch to a different notification method.</p>',
            'user_portal_failed_email_invalid' => '<p>The email address appears to be invalid.</p><p><strong>Action:</strong> Please update the patron\'s email address in Polaris with a valid email address.</p>',

            // Failed SMS messages
            'user_portal_failed_sms'          => '<p>This text message could not be delivered.</p><p><strong>What to do:</strong></p><ul><li>Verify the phone number is correct</li><li>Confirm the phone can receive text messages</li><li>Check if the patron has opted out of texts</li></ul>',
            'user_portal_failed_sms_opt_out'  => '<p>The patron has opted out of receiving text messages from Shoutbomb.</p><p><strong>To restore text notifications:</strong></p><ol><li>The patron must text <strong>START</strong> to the Shoutbomb number</li><li>Or update their notification preference in Polaris to use a different method (Email or Voice)</li></ol>',
            'user_portal_failed_sms_invalid'  => '<p>The phone number appears to be invalid for text messages.</p><p><strong>Action:</strong> Update the patron\'s phone number in Polaris with a valid mobile number.</p>',
            'user_portal_failed_sms_landline' => '<p>This phone number is a landline and cannot receive text messages.</p><p><strong>Options:</strong></p><ul><li>Update to a mobile phone number</li><li>Switch the notification method to Voice or Email</li></ul>',

            // Failed voice messages
            'user_portal_failed_voice'           => '<p>This voice notification could not be completed.</p><p><strong>What to do:</strong></p><ul><li>Verify the phone number is correct</li><li>Ensure the phone is accepting calls</li></ul>',
            'user_portal_failed_voice_no_answer' => '<p>The voice call was not answered after multiple attempts.</p><p><strong>Action:</strong> Verify the patron\'s phone number and consider switching to Email or Text notifications.</p>',

            // Invalid contact messages
            'user_portal_invalid_contact_email' => '<p>This email address has failed for multiple notifications.</p><p><strong>Action required:</strong> The patron\'s email address needs to be updated in Polaris before notifications can be delivered successfully.</p>',
            'user_portal_invalid_contact_phone' => '<p>This phone number has failed for multiple notifications.</p><p><strong>Action required:</strong> The patron\'s phone number needs to be updated in Polaris, or switch to a different notification method.</p>',
        ];

        foreach ($messages as $key => $message) {
            // Only insert if not already exists
            $exists = DB::table('ui_messages')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('ui_messages')->insert([
                    'key'        => $key,
                    'message'    => $message,
                    'label'      => ucwords(str_replace(['user_portal_', '_'], ['', ' '], $key)),
                    'notes'      => 'User portal contextual help message',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ui_messages')
            ->where('key', 'like', 'user_portal_%')
            ->delete()
        ;
    }
};
