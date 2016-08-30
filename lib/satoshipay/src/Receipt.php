<?php
/**
 * This file is part of the SatoshiPay PHP Library.
 *
 * (c) SatoshiPay <hello@satoshipay.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SatoshiPay;

class Receipt
{
    /**
     * @var string
     */
    private $certificate;

    /**
     * @var array
     */
    private $certificateParts;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $expiryTimestamp;

    /**
     * @var array
     */
    private $payloadJson;

    /**
     * @var bool
     */
    private $validity;

    /**
     * Split a payment receipt certificate string into its parts.
     *
     * @param string $certificate
     *
     * @return array|bool Certificate parts or false if certificate is malformed.
     */
    public static function extractCertificateParts($certificate)
    {
        $parts = explode('.', $certificate);
        if (count($parts) < 2 || !$parts[0] || !$parts[1]) {
            return false;
        }

        $signature = $parts[1];
        if (strlen($signature) != 128) {
            return false;
        }

        $payload = $parts[0];
        $payloadJson = json_decode(base64_decode($payload), true);
        if (gettype($payloadJson) != 'array') {
            return false;
        }

        return array(
            'payload' => $payload,
            'payloadJson' => $payloadJson,
            'signature' => $signature
        );
    }

    /**
     * Validate signature hash.
     *
     * @param string $data
     * @param string $secret
     * @param string $signatur
     *
     * @return bool True if signature matches, false if not.
     */
    public static function validateSignature($data, $secret, $signature)
    {
        $hash = hash('sha512', $data . $secret);

        if ($hash == $signature) {
            return true;
        }

        return false;
    }

    /**
     * Constructor.
     *
     * @param string $certificate
     * @param string $secret
     */
    public function __construct($certificate, $secret)
    {
        $this->certificate = (string) $certificate;
        $this->secret = (string) $secret;
    }

    /**
     * Getter for $this->certificateParts (with cache).
     *
     * @see self::extractCertificateParts
     *
     * @return array|bool
     */
    public function getCertificateParts()
    {
        if (!isset($this->certificateParts)) {
            $this->certificateParts = self::extractCertificateParts($this->certificate);
        }

        return $this->certificateParts;
    }

    /**
     * Getter for $this->expiryTimestamp (with cache).
     *
     * @return int Unix timestamp of receipt expiry, 0 if not available.
     */
    public function getExpiryTimestamp()
    {
        if (!isset($this->expiryTimestamp)) {
            $json = $this->getPayloadJson();
            if (isset($json['exp'])) {
                $this->expiryTimestamp = (int) $json['exp'];
            } else {
                $this->expiryTimestamp = 0;
            }
        }

        return $this->expiryTimestamp;
    }

    /**
     * Getter for $this->payloadJson (with cache).
     *
     * @return array|bool Content of payload, false if payload malformed.
     */
    public function getPayloadJson()
    {
        if (!isset($this->payloadJson)) {
            $parts = $this->getCertificateParts();
            if (isset($parts['payloadJson'])) {
                $this->payloadJson = $parts['payloadJson'];
            } else {
                $this->payloadJson = false;
            }
        }

        return $this->payloadJson;
    }

    /**
     * Getter for $this->validity (with cache).
     *
     * @see self::validateCertificate
     *
     * @return bool True if valid, false if not.
     */
    public function getValidity()
    {
        if (!isset($this->validity)) {
            $this->validity = $this->validateCertificate();
        }

        return $this->validity;
    }

    /**
     * Check if receipt expired.
     *
     * @return bool True if expired, false if not.
     */
    public function isExpired()
    {
        $date = new \DateTime();

        if ($this->getExpiryTimestamp() > $date->getTimestamp()) {
            return false;
        }

        return true;
    }

    /**
     * Shortcut method for self::getValidity().
     *
     * @return bool True if valid, false if not.
     */
    public function isValid()
    {
        return $this->getValidity();
    }

    /**
     * Validate receipt certificate.
     *
     * @return bool True if valid, false if not.
     */
    public function validateCertificate()
    {
        $parts = $this->getCertificateParts();

        if ($parts === false) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return self::validateSignature($parts['payload'], $this->secret, $parts['signature']);
    }
}
