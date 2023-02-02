<?php

namespace App\Console\DKIM;

use App\Libs\Config\DKIMConfig;
use DateTime;
use Ddeboer\Imap\Message;
use Ddeboer\Imap\Search\Text\Subject;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class FetchAndProcessReportsCommand extends Command
{
	protected static $defaultName = 'dkim:fetchAndProcessReports';

	/** @var OutputInterface */
	private $output;

	public function __construct(
		private DKIMConfig $config,
	) {
		parent::__construct();
	}

	protected function configure()
	{
		$this->setName(self::$defaultName)
			->setDescription('Fetches DKIM reports from mailbox, process them and shows summary.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->output = $output;

		$server = new Server($this->config->mbox['host']);
		$connection = $server->authenticate($this->config->mbox['user'], $this->config->mbox['password']);
		$search = (new SearchExpression)->addCondition(new Subject('Report domain'));
		$mailbox = $connection->getMailbox('DKIM');
		$messages = $mailbox->getMessages($search);

		foreach ($messages as $message) {
			$this->processMessage($message);
		}

		$mailbox->expunge();

		return 0;
	}

	/**
	 * @param Message $message
	 */
	private function processMessage(Message $message)
	{
		$attachments = $message->getAttachments();
		if ($attachments) {
			foreach ($attachments as $attachment) {
				$this->processAttachment($attachment);
			}
		}

		if (isset($this->config->mbox['deleteProcessed']) && $this->config->mbox['deleteProcessed'] === TRUE) {
			$message->delete();
		}
	}

	/**
	 * @param Message\Attachment $attachment
	 */
	private function processAttachment(Message\Attachment $attachment)
	{
		if ($attachment->getSubtype() !== 'ZIP') {
			return;
		}

		$zipFile = tempnam('/tmp', 'DKIM');
		file_put_contents($zipFile, $attachment->getDecodedContent());
		$zip = new ZipArchive;
		$zip->open($zipFile);
		$xml = simplexml_load_string($zip->getFromIndex(0));
		unlink($zipFile);

		$begin = (new DateTime)->setTimestamp((int) $xml->report_metadata->date_range->begin);
		$end = (new DateTime)->setTimestamp((int) $xml->report_metadata->date_range->end);
		$reporter = $xml->report_metadata->org_name;

		foreach ($xml->record as $record) {
			$this->processRecord($record, $begin, $end, $reporter);
		}
	}

	/**
	 * @param SimpleXMLElement $record
	 * @param DateTime         $begin
	 * @param DateTime         $end
	 * @param string           $reporter
	 */
	private function processRecord(SimpleXMLElement $record, DateTime $begin, DateTime $end, $reporter)
	{
		$spfResult = $record->auth_results->spf->result;
		$dkimResult = $record->auth_results->dkim->result;

		if ($spfResult !== 'pass') {
			$this->output->writeln(
				"[{$begin->format('Y-m-d')} - {$end->format('Y-m-d')} {$reporter}] " .
				"SPF check for IP {$record->row->source_ip} " .
				"with from header {$record->identifiers->header_from} " .
				"resulted as {$spfResult} {$record->row->count} times"
			);
		}

		if ($dkimResult !== 'pass') {
			$this->output->writeln(
				"[{$begin->format('Y-m-d')} - {$end->format('Y-m-d')} {$reporter}] " .
				"DKIM check for IP {$record->row->source_ip} " .
				"with from header {$record->identifiers->header_from} " .
				"resulted as {$dkimResult} {$record->row->count} times"
			);
		}
	}

}
