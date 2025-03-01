<?php

namespace PayEx\Api\Service\Transaction\Resource\Response\Data;

use PayEx\Api\Service\Data\ResourceInterface;

/**
 * Capture object interface
 *
 * @api
 */
interface CaptureInterface extends ResourceInterface
{
    const ID = 'id';

    const TRANSACTION = 'transaction';

    /**
     * @return string
     */
    public function getId();

    /**
     * @param string $captureId
     * @return $this
     */
    public function setId($captureId);

    /**
     * @return TransactionInterface
     */
    public function getTransaction();

    /**
     * @param TransactionInterface $transaction
     * @return $this
     */
    public function setTransaction($transaction);
}
