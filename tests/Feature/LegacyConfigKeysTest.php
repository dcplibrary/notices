<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A host app that published config/notices.php before the keys were renamed
 * must keep working with the values it already had.
 */
class LegacyConfigKeysTest extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // What an older published config/notices.php looks like once loaded.
        $app['config']->set('notices.microsoft', [
            'mailbox' => 'reports@example.org',
            'folders' => ['inbox' => 'Inbox', 'shoutbomb' => 'Inbox/Old Folder'],
        ]);
        $app['config']->set('notices.polaris', ['ftp' => ['host' => 'ftp.example.org', 'root' => '/old/root']]);
        $app['config']->set('notices.import', ['local_storage' => '/old/storage']);
        $app['config']->set('notices.query_logging_threshold', 250);
    }

    #[Test]
    public function old_key_names_are_carried_over_to_the_new_ones(): void
    {
        $this->assertSame('Inbox/Old Folder', config('notices.microsoft.folder_shoutbomb'));
        $this->assertSame('Inbox', config('notices.microsoft.folder_inbox'));
        $this->assertSame('/old/root', config('notices.polaris.ftp.path'));
        $this->assertSame('/old/storage', config('notices.import.storage'));
        $this->assertSame(250, config('notices.query_threshold_ms'));
    }
}
