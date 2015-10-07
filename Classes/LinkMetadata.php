<?php
namespace Aoe\Linkhandler;
use Aoe\Linkhandler\Exception\LinkMetadataFormatException;

/**
 * @author Thorsten Boock <tboock@codegy.de>
 */
class LinkMetadata {

	/**
	 * @var string
	 */
	private $anchorType;

	/**
	 * @var string
	 */
	private $databaseTable;

	/**
	 * @var int
	 */
	private $recordUid;

	/**
	 * @param string $metadata
	 */
	public function __construct($metadata) {
		$metadataParts = $this->splitMetadata($metadata);
		list($this->anchorType, $this->databaseTable, $this->recordUid) = $metadataParts;
	}

	/**
	 * @param string $metadata
	 * @return array
	 * @throws LinkMetadataFormatException
	 */
	private function splitMetadata($metadata) {
		$this->validateMetadataFormat($metadata);
		$parts = explode(':', $metadata);
		array_shift($parts);
		return $parts;
	}

	/**
	 * @param string $metadata
	 * @throws LinkMetadataFormatException if the passed record identifier is invalid
	 */
	private function validateMetadataFormat($metadata) {
		if (strpos($metadata, 'record:') !== 0) {
			throw new LinkMetadataFormatException('Link metadata must begin with "record:".', 1444232530);
		}

		if (substr_count($metadata, ':') !== 3) {
			throw new LinkMetadataFormatException('Link metadata is supposed to consist of 4 parts separated by colon.', 1444232524);
		}
	}

	/**
	 * @return string
	 */
	public function getAnchorType() {
		return $this->anchorType;
	}

	/**
	 * @return string
	 */
	public function getDatabaseTable() {
		return $this->databaseTable;
	}

	/**
	 * @return int
	 */
	public function getRecordUid() {
		return $this->recordUid;
	}

}