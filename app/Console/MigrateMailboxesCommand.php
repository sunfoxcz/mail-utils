<?php declare(strict_types=1);

namespace App\Console;

use App\Libs\Config\MigrateConfig;
use App\Libs\Logging\ConsoleLogger;
use Ddeboer\Imap\Connection;
use Ddeboer\Imap\ImapResource;
use Ddeboer\Imap\Mailbox;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ddeboer\Imap\Server;

class MigrateMailboxesCommand extends Command
{
    protected static $defaultName = 'migrateMailboxes';

    private Server $sourceServer;
    private Server $destinationServer;

    public function __construct(
        private ConsoleLogger $logger,
        private MigrateConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)
            ->setDescription('Migrates mailboxes from one server to another.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->sourceServer = new Server($this->config->server['source']);
        $this->destinationServer = new Server($this->config->server['destination']);

        foreach ($this->config->mailboxes as $address => $settings)
        {
            $this->logger->info('Processing mailbox {address}', ['address' => $address]);
            $this->processMailbox($settings);
        }

        return 0;
    }

    private function processMailbox(ArrayHash $settings): void
    {
        $sourceConnection = $this->sourceServer->authenticate(
            $settings['source']['login'], $settings['source']['pass']
        );

        $destinationConnection = $this->destinationServer->authenticate(
            $settings['destination']['login'], $settings['destination']['pass']
        );

        $sourceMailboxes = $sourceConnection->getMailboxes();
        $this->createDestinationFolders($sourceMailboxes, $destinationConnection);

        foreach ($sourceMailboxes as $sourceMbox) {
            if ($sourceMbox->getAttributes() & \LATT_NOSELECT) {
                continue;
            }

            $destinationMbox = $destinationConnection->getMailbox($this->normalizeName($sourceMbox->getName()));
            $this->processMailboxFolder($sourceConnection, $sourceMbox, $destinationMbox);
        }
    }

    private function processMailboxFolder(
        Connection $sourceConnection,
        Mailbox $sourceMbox,
        Mailbox $destinationMbox
    ): void {
        $count = $sourceMbox->count();
        $imapConnection = $sourceConnection->getResource()->getStream();

        $this->logger->info('[{folder}] Transferind {number} messages.', [
            'folder' => $sourceMbox->getName(),
            'number' => $count,
        ]);

        $i = 0;
        $messageNumbers = imap_search($imapConnection, 'ALL', \SE_UID);
        if ($messageNumbers) {
            foreach ($messageNumbers as $number) {
                $info = imap_fetch_overview($imapConnection, (string) $number, \FT_UID);
                $header = imap_fetchheader($imapConnection, $number, \FT_UID);
                $body = imap_body($imapConnection, $number, \FT_UID | \FT_PEEK);

                $destinationMbox->addMessage($header . "\r\n" . $body);

                if ($info[0]->seen) {
                    imap_setflag_full($imapConnection, $number, '\\SEEN');
                }

                imap_delete($imapConnection, $number, \FT_UID);

                $i++;
                $this->logger->info("\r[{folder}] Transfered {current} out of {total} messages.", [
                    'folder' => $sourceMbox->getName(),
                    'current' => $i,
                    'total' => $count,
                ]);
            }
        }

        $this->logger->info("\r[{folder}] Transfered {current} out of {total} messages.", [
            'folder' => $sourceMbox->getName(),
            'current' => $i,
            'total' => $count,
        ]);
        $sourceConnection->expunge();
    }

    /**
     * @param Mailbox[] $sourceMailboxes
     * @param Connection $destinationConnection
     * @return void
     */
    private function createDestinationFolders(array $sourceMailboxes, Connection $destinationConnection): void
    {
        $destinationFolders = array_keys($destinationConnection->getMailboxes());

        foreach (array_keys($sourceMailboxes) as $folder) {
            $folder = $this->normalizeName($folder);
            if (!in_array($folder, $destinationFolders)) {
                $destinationConnection->createMailbox($folder);
                $this->logger->info("Created folder {folder}", ['folder' => $folder]);
            }
        }
    }

    private function normalizeName(string $folder): string
    {
        //$folder = Strings::toAscii(mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP"));

        if (Strings::startsWith($folder, '[Gmail]/')) {
            $folder = Strings::substring($folder, 8);
        }

        if ($folder !== 'INBOX' && !Strings::startsWith($folder, 'INBOX.')) {
            $folder = 'INBOX.' . $folder;
        }

        return $folder;
    }
}
