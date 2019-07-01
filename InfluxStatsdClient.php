<?php

class InfluxStatsdClient {

	private $host;
	private $port;

	private $mtu;

	private $data;
	private $tags;

	// --------------------------------------------------------------------
	// CONSTRUCTION

    public function __construct ($host = 'localhost', $port = '8125', $mtu = null) {
        $this->host = $host;
        $this->port = $port;
        $this->mtu = $mtu;
		$this->data = array();
    }

    public function setGlobalTags ($tags) {
        $this->tags = $tags;
	}


	// --------------------------------------------------------------------
	// POINT COLLECTION

	/**
     * Records a value, generally a time duration.
	 * Stores its statistical distribution as well as its mean.
	 */
	public function timing ($variable, $time, $tags = array()) {
        $this->data[] = sprintf('%s%s:%s|ms', $variable, $this->buildTagString(array_merge($this->tags, $tags)), $time);
    }

	/**
     * Increments a counter.
	 */
	public function increment ($variable, $tags = array()) {
        $this->data[] = $variable.$this->buildTagString(array_merge($this->tags, $tags)).':1|c';
    }

	/**
     * Decrements a counter.
	 */
	public function decrement ($variable, $tags = array()) {
        $this->data[] = $variable.$this->buildTagString(array_merge($this->tags, $tags)).':-1|c';
    }

	/**
     * Add value to a counter.
	 */
	public function measure ($variable, $value, $tags = array()) {
        $this->data[] = sprintf('%s%s:%s|c', $variable, $this->buildTagString(array_merge($this->tags, $tags)), $value);
    }

	/**
     * Record a sample value into a gauge.
	 * A gauge is stores relative amounts, whereas a counter stores absolute amounts.
	 */
	public function gauge ($variable, $value, $tags = array()) {
        $this->data[] = sprintf('%s%s:%s|g', $variable, $this->buildTagString(array_merge($this->tags, $tags)), $value);
    }

	/**
	 * Record an integer index.
	 * Stores number of unique indexes.
	 */
	public function set ($variable, $value, $tags = array()) {
        $this->data[] = sprintf('%s%s:%s|s', $variable, $this->buildTagString(array_merge($this->tags, $tags)), $value);
    }


	// --------------------------------------------------------------------
	// FLUSHING

	public function flush () {
        if (!$this->data) {
            return;
        }

        $fp = fsockopen('udp://'.$this->host, $this->port, $errno, $errstr, 1.0);

        if (!$fp) {
            return;
        }

		if (empty($this->mtu)) {
			$this->sendMetricsUnitarily($fp);
		} else {
			$this->sendMetricsInBatches($fp);
        }

        fclose($fp);

        $this->data = array();
    }


	// --------------------------------------------------------------------
	// HELPERS

	protected function buildTagString ($tags) {
		$tagString = http_build_query($tags, '', ',');
        $tagString = (strlen($tagString) > 0 ? ','.$tagString : $tagString);
		return $tagString;
    }

	protected function buildUdpPacket ($packet, $metric) {
		if (empty($packet))
			return $metric;
		else
			return $packet . "\n" . $metric;
	}

	protected function sendMetricsUnitarily ($fp) {
		$level = error_reporting(0);
		foreach ($this->data as $line) {
			fwrite($fp, $line);
		}
		error_reporting($level);
	}

	protected function sendMetricsInBatches ($fp) {
		$packet = '';

		$level = error_reporting(0);

		foreach ($this->data as $line) {
			$potentialPacket = $this->buildUdpPacket($packet, $line);
			if (strlen($potentialPacket) > $this->mtu) {
				fwrite($fp, $packet);
				$packet = $this->buildUdpPacket('', $line);
			} else {
				$packet = $potentialPacket;
			}
		}

		if (! empty($packet)) {
			fwrite($fp, $packet);
		}

		error_reporting($level);
	}
}
