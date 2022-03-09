<?php

include_once __DIR__ . "/../../../../cron/BaseTask.php";

class MassTransfersRequeue extends BaseTask
{
	const NAME = 'mass-transfers-requeue';
	const CHUNK_SIZE = 1;
	const PAYMENT_ATTEMPT_TTL_DAYS = 30;
	const MAX_TRANSFER_PAYMENT_ATTEMPTS = 30;

	public function __construct()
	{
		parent::__construct(self::NAME);
	}

	protected function action($args)
	{
		$this->log("starting worker");

		$task = $this->findOneUnprocessedTask();

		if (!$task) {
			$this->log("no active tasks found");
			return;
		}

		$this->updateTaskStatus($task['id'], 'in_progress');

		$transferIds = $task['transfer_ids'] ?? [];

		$this->log(count($transferIds) . " transfers fetched");

		foreach (array_chunk($transferIds, self::CHUNK_SIZE) as $transfersChunk) {
			$validTransactionIds = $this->getValidTransactionIds($transfersChunk);

			if (!count($validTransactionIds)) {
				$this->log(sprintf("No valid transactions found in (%s)", implode(', ', $transfersChunk)));
				continue;
			}

			$this->createPaymentRequeueTransfers($task['id'], $validTransactionIds);
			$this->updateTransferPaymentAttemptsQtyByRequeueId(
				self::MAX_TRANSFER_PAYMENT_ATTEMPTS - $task['attempts_qty'],
				$task['id'],
				$validTransactionIds
			);
		}

		$this->updateTaskStatus($task['id'], 'processed');

		$this->log("worker successfully finished");
	}

	private function findOneUnprocessedTask(): ?array
	{
		$result = $this->query("
			select ...
			from payment_requeue 
			where status = 'created' limit 1
		")->fetch_assoc();

		if ($result) {
			$result['transfer_ids'] = json_decode($result['transfer_ids']);
		}

		return $result;
	}

	private function updateTaskStatus(int $requeueId, string $status) {
		$this->query("update payment_requeue set status = '$status' where id = $requeueId");
	}

}
