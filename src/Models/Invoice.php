<?php

namespace Academe\OpenErpApi\Models;

/**
 * Invoice model.
 */

use Money\Currencies\ISOCurrencies;
use Money\Parser\DecimalMoneyParser;

class Invoice
{
    const DIRECTION_IN = 'in';
    const DIRECTION_OUT = 'out';

    const TYPE_INVOICE = 'invoice';
    const TYPE_REFUND = 'refund';

    protected $sourceData;

    public function __construct(array $data = [])
    {
        // Store away the source data.
        $this->sourceData = $data;
    }

    /**
     * Get a data field using a "dot notation" path.
     */
    public function getItem($key, $default = null)
    {
        $target = $this->sourceData;

        if (is_null($key) || trim($key) == '') {
            return $target;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
                continue;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
                continue;
            }

            return static::_value($default);
        }
        return $target;
    }

    /**
     * @param $value
     */
    protected static function _value($value)
    {
        if ($value instanceof Closure) {
            return $value();
        } else {
            return $value;
        }
    }

    protected function convertMoney($currencyCode, $amountMajor)
    {
        if ($amountMajor === null) {
            return;
        }

        if ($currencyCode === null || ! is_string($currencyCode)) {
            return;
        }

        $currencies = new ISOCurrencies();

        $moneyParser = new DecimalMoneyParser($currencies);

        $money = $moneyParser->parse("$amountMajor", $currencyCode);

        return $money;
    }

    /** 
     * @return Money|null
     * Always abs value.
     */
    public function getAmountTotal()
    {
        return $this->convertMoney(
            $this->getItem('currency_id.1'),
            $this->getItem('amount_total')
        );
    }

    /** 
     * @return Money|null
     * Value is signed.
     */
    public function getAmountTotalSigned()
    {
        return $this->getAmountTotal()
            ->absolute()
            ->multiply($this->getSign());
    }

    /** 
     * @return Money|null
     * Always abs value.
     */
    public function getResidual()
    {
        return $this->convertMoney(
            $this->getItem('currency_id.1'),
            $this->getItem('residual')
        );
    }

    /** 
     * @return Money|null
     * Signed: +ve for out invoice and -ve for out refund.
     */
    public function getResidualSigned()
    {
        return $this->getResidual()
            ->absolute()
            ->multiply($this->getSign());
    }

    public function getSign()
    {
        // If it is an outgoing refund then the sign will be negative.

        if (
            $this->getItemDirection() === static::DIRECTION_OUT
            && $this->getItemType() == static::TYPE_REFUND
        ) {
            return -1;
        }

        return 1;
    }

    /**
     * The direction the item moved.
     * "out" is an invoice or refund generated by the system and
     * "in" is an invoice or refund received from an external partner.
     * Opwall only uses "out" at this time.
     */
    public function getItemDirection()
    {
        $type = $this->getItem('type');

        if (strpos($type, '_') === false) {
            // Not known.
            return;
        }

        list($direction, $type) = explode('_', strtolower($type), 2);

        return $direction;
    }

    /**
     * The type of the item moved.
     * "invoice" is a request for payment and "refund" is a refunc of payment.
     */
    public function getItemType()
    {
        $type = $this->getItem('type');

        if (strpos($type, '_') === false) {
            // Not known.
            return;
        }

        list($direction, $type) = explode('_', strtolower($type), 2);

        return $type;
    }

    /**
     * The partner being invoiced (for "out" invoices or refunds).
     */
    public function getPartnerId()
    {
        return $this->getItem('partner_id.0');
    }

    public function getPartnerName()
    {
        return $this->getItem('partner_id.1');
    }
}
