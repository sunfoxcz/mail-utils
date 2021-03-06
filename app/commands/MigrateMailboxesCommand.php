<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Text\Subject;

// -------------------------------------------------------------------------------------------------

class MigrateMailboxesCommand extends Command
{
	/** @var \Symfony\Component\Console\Input\InputInterface */
	private $output;

	/** @var \Ddeboer\Imap\Server */
	private $sourceServer;

	/** @var \Ddeboer\Imap\Server */
	private $destinationServer;

	// -------------------------------------------------------------------------------------------------

	protected function configure()
	{
		$this->setName('migrateMailboxes')
			->setDescription('Migrates mailboxes from one server to another.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$config = $this->getHelper('container')->getContainer()->parameters['migrate'];

		$this->output = $output;
		$this->sourceServer = new Server($config['server']['source']);
		$this->destinationServer = new Server($config['server']['destination']);

		foreach ($config['mailboxes'] as $address => $settings)
		{
			$this->output->writeLn("<comment>Processing mailbox {$address}:</comment>");
			$this->processMailbox($settings);
		}
	}

	private function processMailbox(array $settings)
	{
		$sourceConnection = $this->sourceServer->authenticate(
			$settings['login'], $settings['pass']
		);

		$destinationConnection = $this->destinationServer->authenticate(
			$settings['login'], $settings['pass']
		);

		$sourceFolders = $this->getMailboxFolders($sourceConnection);
		$this->createDestinationFolders(array_keys($sourceFolders), $destinationConnection);

		foreach ($sourceFolders as $name => $sourceMbox)
		{
			$folder = \Nette\Utils\Strings::toAscii(mb_convert_encoding($sourceMbox->getName(), "UTF-8", "UTF7-IMAP"));
			$destinationMbox = $destinationConnection->getMailbox($folder);

			$this->processMailboxFolder(
				$folder, $sourceConnection->getResource(),
				$sourceMbox, $destinationMbox
			);
		}
	}

	private function processMailboxFolder($folder, $sourceResource, \Ddeboer\Imap\Mailbox $sourceMbox,
											\Ddeboer\Imap\Mailbox $destinationMbox)
	{
		// Select proper mbox
		$count = $sourceMbox->count();

		$this->output->write("[{$folder}] Transfering {$count} messages.");

		$i = 0;
		$messageNumbers = imap_search($sourceResource, 'ALL', \SE_UID);
		if ($messageNumbers)
		{
			foreach ($messageNumbers as $number)
			{
				$info = imap_fetch_overview($sourceResource, $number, \FT_UID);
				$header = imap_fetchheader($sourceResource, $number, \FT_UID);
				$body = imap_body($sourceResource, $number, \FT_UID | \FT_PEEK);

				$destinationMbox->addMessage($header."\r\n".$body);

				if ($info[0]->seen)
				{
					imap_setflag_full($sourceResource, $number, '\\SEEN');
				}

				imap_delete($sourceResource, $number, \FT_UID);

				$i++;
				$this->output->write("\r[{$folder}] Transfered {$i} out of {$count} messages.");
			}
		}

		$this->output->writeLn("\r<info>[{$folder}] Transfered {$i} out of {$count} messages.</info>");
		$sourceMbox->expunge();
	}

	private function createDestinationFolders(array $folderList, \Ddeboer\Imap\Connection $destinationConnection)
	{
		$destinationFolders = array_keys($this->getMailboxFolders($destinationConnection));

		foreach ($folderList as $folder)
		{
			$folder = \Nette\Utils\Strings::toAscii(mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP"));

			if (!in_array($folder, $destinationFolders))
			{
				$destinationConnection->createMailbox($folder);
				// $this->output->writeLn("Created folder {$folder}");
			}
		}
	}

	private function getMailboxFolders(\Ddeboer\Imap\Connection $connection)
	{
		$folderList = [];

		$folders = $connection->getMailboxes();
		foreach ($folders as $folder)
		{
			$folderList[$folder->getName()] = $folder;
		}

		return $folderList;
	}

}
