<?php

namespace App\Console\DKIM;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Text\Subject;

// -------------------------------------------------------------------------------------------------

class FetchAndProcessReportsCommand extends Command
{

	protected function configure()
	{
		$this->setName('dkim:fetchAndProcessReports')
			->setDescription('Fetches DKIM reports from mailbox, process them and shows summary.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$config = $this->getHelper('container')->getContainer()->parameters['dkim'];

		$server = new Server($config['mbox']['host']);
		$connection = $server->authenticate($config['mbox']['user'], $config['mbox']['password']);
		$search = (new SearchExpression)->addCondition(new Subject('Report domain'));
		$mailbox = $connection->getMailbox('DKIM');
		$messages = $mailbox->getMessages($search);

		foreach ($messages as $message)
		{
			$attachments = $message->getAttachments();
			if ($attachments)
			{
				foreach ($attachments as $attachment)
				{
					if ($attachment->getSubtype() == 'ZIP')
					{
						$zipFile = tempnam('/tmp', 'DKIM');
						file_put_contents($zipFile, $attachment->getDecodedContent());
						$zip = new \ZipArchive;
						$zip->open($zipFile);
						$xml = simplexml_load_string($zip->getFromIndex(0));
						unlink($zipFile);

						$begin = (new \DateTime)->setTimestamp((int)$xml->report_metadata->date_range->begin);
						$end = (new \DateTime)->setTimestamp((int)$xml->report_metadata->date_range->end);
						$reporter = $xml->report_metadata->org_name;

						foreach ($xml->record as $record)
						{
							$spfResult = $record->auth_results->spf->result;
							$dkimResult = $record->auth_results->dkim->result;

							if ($spfResult != 'pass')
							{
								$output->writeLn(
									"[{$begin->format('Y-m-d')} - {$end->format('Y-m-d')} {$reporter}] " .
									"SPF check for IP {$record->row->source_ip} " .
									"with from header {$record->identifiers->header_from} " .
									"resulted as {$spfResult} {$record->row->count} times"
								);
							}

							if ($dkimResult != 'pass')
							{
								$output->writeLn(
									"[{$begin->format('Y-m-d')} - {$end->format('Y-m-d')} {$reporter}] " .
									"SPF check for IP {$record->row->source_ip} " .
									"with from header {$record->identifiers->header_from} " .
									"resulted as {$dkimResult} {$record->row->count} times"
								);
							}
						}
					}
				}
			}

			if (isset($config['mbox']['deleteProcessed']) && $config['mbox']['deleteProcessed'] === TRUE)
			{
				$message->delete();
			}
		}

		$mailbox->expunge();
	}

}
