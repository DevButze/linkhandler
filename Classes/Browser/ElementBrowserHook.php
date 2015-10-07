<?php
namespace Aoe\Linkhandler\Browser;

/*                                                                        *
 * This script belongs to the TYPO3 extension "linkhandler".              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 3 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Aoe\Linkhandler\ConfigurationManager;
use Aoe\Linkhandler\Exception\LinkMetadataFormatException;
use Aoe\Linkhandler\LinkMetadata;
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use \TYPO3\CMS\Core\ElementBrowser\ElementBrowserHookInterface;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Rtehtmlarea\BrowseLinks;

/**
 * @author Thorsten Boock <tboock@codegy.de>
 * @author Alexander Stehlik <astehlik.deleteme@intera.de>
 * @author Daniel Pötzinger <daniel.poetzinger@aoemedia.de>
 *
 * Note: type hinting is not possible for interface methods, because ElementBrowserHookInterface is broken.
 */
class ElementBrowserHook implements ElementBrowserHookInterface {

	/**
	 * @var FrontendBackendUserAuthentication
	 */
	protected $backendUserAuthentication;

	/**
	 * @var ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @var LanguageService
	 */
	protected $languageService;

	/**
	 * @var BrowseLinks
	 */
	protected $browseLinks;

	/**
	 * Configuration settings for different anchor types.
	 * These settings are retrieved from the PageTS configuration (mod.tx_linkhandler).
	 *
	 * @var array
	 */
	protected $anchorTypeSettings;

	/**
	 * Initializes global objects
	 */
	public function __construct() {
		$this->backendUserAuthentication = $GLOBALS['BE_USER'];
		$this->languageService = $GLOBALS['LANG'];
		$this->configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
	}

	/**
	 * Initializes the hook object
	 *
	 * @param \TYPO3\CMS\Rtehtmlarea\BrowseLinks $browseLinks
	 * @param array $params
	 */
	public function init($browseLinks, $params) {
		$this->browseLinks = $browseLinks;
		$this->initializeAnchorSettings();

		if (!$this->richTextEditorSupportsUserLinks()) {
			$this->appendUserLinkJavascriptMethodToDocument();
		}
	}

	/**
	 * Retrieves the anchor settings from the PageTS configuration.
	 */
	protected function initializeAnchorSettings() {
		$pageUid = $this->getCurrentPageUid();
		$this->configurationManager->loadConfiguration(NULL, $pageUid);
		$this->anchorTypeSettings = $this->configurationManager->getTabsConfiguration();
	}

	/**
	 * @return int|NULL
	 */
	private function getCurrentPageUid() {
		if ($this->isEmbeddedInRichTextEditor()) {
			$confParts = explode(':', $this->browseLinks->RTEtsConfigParams);
			return $confParts[5];
		}

		return NULL;
	}

	/**
	 * Determines whether the element browser was opened from a rich text editor.
	 */
	public function isEmbeddedInRichTextEditor() {
		return $this->browseLinks->mode === 'rte';
	}

	/**
	 * Checks if the used TYPO3 / RTE version includes support for user links.
	 * User link support has been dropped in TYPO3 version 7.4.
	 *
	 * @return bool
	 */
	private function richTextEditorSupportsUserLinks() {
		return VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) <= 7004000;
	}

	/**
	 * Appends the link_spec javascript method to the document. This method is required by the linkhandler extension
	 * but has been removed from TYPO3 version 7.4.
	 */
	private function appendUserLinkJavascriptMethodToDocument() {
		// note: crappy old javascript code was copy pasted from the TYPO3 core
		$this->browseLinks->doc->JScode .= '
			<script>
				function link_spec(theLink) {
					if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
					if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
					if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
					browse_links_setAdditionalValue("data-htmlarea-external", "");
					plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
					return false;
				}
			</script>
			';
	}

	/**
	 * Adds additional items to the passed list of allowed items.
	 *
	 * @param array $allowedItems currently allowed items
	 * @return array currently allowed items plus added items
	 */
	public function addAllowedItems($allowedItems) {
		$configuredAnchorTypes = array_keys($this->anchorTypeSettings);
		$allowedItems = array_merge($allowedItems, $configuredAnchorTypes);
		return $allowedItems;
	}

	/**
	 * Returns the current link target (different for RTE and normal "browselinks" (WTF?!))
	 *
	 * @return string
	 */
	public function getCurrentValue() {
		if ($this->isEmbeddedInRichTextEditor()) {
			$value = $this->browseLinks->curUrlInfo['value'];
		} else {
			$value = $this->browseLinks->P['currentValue'];
		}

		return $value;
	}

	/**
	 * @return BrowseLinks
	 */
	public function getBrowseLinks() {
		return $this->browseLinks;
	}

	/**
	 * Returns a new tab for the browse links wizard. Will be called
	 * by the parent link browser.
	 *
	 * @param string $anchorType current link selector action
	 * @return string a tab for the selected link action
	 * @throws \Exception if the active tab was not configured
	 */
	public function getTab($anchorType) {
		$elementBrowser = $this->buildElementBrowser($anchorType);
		$content = $this->isEmbeddedInRichTextEditor() ? $this->browseLinks->addAttributesForm() : '';
		$content .= $elementBrowser->renderContent();
		return $content;
	}

	/**
	 * @param string $anchorType
	 * @return ElementBrowser
	 * @throws \Exception if the passed anchor type was not configured
	 */
	protected function buildElementBrowser($anchorType) {
		$configuration = $this->configurationManager->getSingleTabConfiguration($anchorType);

		if ($configuration === NULL) {
			throw new \Exception('Missing configuration for anchor type ' . $anchorType . '.', 1444211551);
		}

		/** @var ElementBrowser $elementBrowser */
		$elementBrowser = GeneralUtility::makeInstance(ElementBrowser::class, $this->browseLinks, $configuration);
		$elementBrowser->init();

		return $elementBrowser;
	}

	/**
	 * Modifies the menu definition and returns it
	 *
	 * @param array $menuDef menu definition
	 * @return array modified menu definition
	 */
	public function modifyMenuDefinition($menuDef) {
		$currentScriptUrlWithTrailingParameterSeparator = $this->getCurrentScriptUrlWithTrailingParameterSeparator();

		foreach ($this->anchorTypeSettings as $anchorType => $configuration) {
			$escapedTabUrl = GeneralUtility::quoteJSvalue($currentScriptUrlWithTrailingParameterSeparator . 'act=' . $anchorType);

			$menuDef[$anchorType]['isActive'] = $this->browseLinks->act === $anchorType;
			$menuDef[$anchorType]['label'] = $this->languageService->sL($configuration['label'], TRUE);
			$menuDef[$anchorType]['url'] = '#';
			$menuDef[$anchorType]['addParams'] = 'onclick="jumpToUrl(' . $escapedTabUrl . ');return false;"';
		}

		return $menuDef;
	}

	/**
	 * Returns the URL to the current script and a trailing ? or & so that
	 * a new URL parameter can be appended.
	 *
	 * @return string
	 */
	private function getCurrentScriptUrlWithTrailingParameterSeparator() {
		$separator = strpos($this->browseLinks->thisScript, '?') === FALSE ? '?' : '&';
		$currentScriptUrlWithTrailingParameterSeparator = $this->browseLinks->thisScript . $separator;
		return $currentScriptUrlWithTrailingParameterSeparator;
	}

	/**
	 * Checks the current URL and returns a info array. This is used to
	 * tell the link browser which is the current tab based on the current URL.
	 * function should at least return the $info array.
	 *
	 * @param string $href
	 * @param string $siteUrl
	 * @param array $info Current info array.
	 * @return array $info a infoarray for browser to tell them what is current active tab
	 */
	public function parseCurrentUrl($href, $siteUrl, $info) {
		// Depending on link and setup the href string can contain complete absolute link
		if (substr($href, 0, 7) === 'http://') {
			if ($_href = strstr($href, '?id=')) {
				$href = substr($_href, 4);
			} else {
				$href = substr(strrchr($href, '/'), 1);
			}
		}

		try {
			$modifiedInfo = $this->buildLinkInfo($href);
			$info = array_merge($info, $modifiedInfo);
		} catch (LinkMetadataFormatException $exception) {
			// do not modify the passed info array if passed metadata could not be processed
		}

		return $info;
	}

	/**
	 * @param string $href
	 * @return array
	 */
	protected function buildLinkInfo($href) {
		$linkMetadata = new LinkMetadata($href);
		$databaseTable = $linkMetadata->getDatabaseTable();
		$recordUid = $linkMetadata->getRecordUid();
		$info = [
			'act' => $linkMetadata->getAnchorType(),
			'recordTable' => $databaseTable,
			'recordUid' => $recordUid,
			'info' => $this->buildLinkLabel($databaseTable, $recordUid)
		];
		return $info;
	}

	/**
	 * @param string $databaseTable
	 * @param int $recordUid
	 * @return string
	 */
	protected function buildLinkLabel($databaseTable, $recordUid) {
		$linkLabel = '';
		$record = BackendUtility::getRecord($databaseTable, $recordUid);

		if (isset($GLOBALS['TCA'][$databaseTable]['ctrl']['title'])) {
			$labelIdentifier = $GLOBALS['TCA'][$databaseTable]['ctrl']['title'];
			$linkLabel = $this->languageService->sL($labelIdentifier);
		}

		if ($record !== NULL) {
			$recordLabel = BackendUtility::getRecordTitle($databaseTable, $record);
			$linkLabel .= ': ' . $recordLabel;
		}

		return $linkLabel;
	}

}