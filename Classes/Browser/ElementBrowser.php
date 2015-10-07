<?php
namespace Aoe\Linkhandler\Browser;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Rtehtmlarea\BrowseLinks;

/**
 * @author Thorsten Boock <tboock@codegy.de>
 * @author Alexander Stehlik <astehlik.deleteme@intera.de>
 * @author Daniel Pötzinger <daniel.poetzinger@aoemedia.de>
 */
class ElementBrowser extends \TYPO3\CMS\Recordlist\Browser\ElementBrowser {

	/**
	 * @var BrowseLinks
	 */
	protected $browseLinks;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param BrowseLinks $browseLinks
	 * @param array $settings
	 */
	public function __construct(BrowseLinks $browseLinks, array $settings) {
		parent::__construct();

		$this->browseLinks = $browseLinks;
		$this->settings = $settings;
	}

	/**
	 * @return string
	 */
	public function renderContent() {
		$expandedPage = $this->expandTheLinkedElementsParentPage();
		$tree = $this->renderTree($expandedPage);
		$elements = $this->renderElements();
		$temporaryTreeMountCancelNotice = $this->getTemporaryTreeMountCancelNotice();

		$content = '
			<table border="0" cellpadding="0" cellspacing="0" id="typo3-linkPages" class="tx-linkbrowser-tab">
				<tr>
					<td class="c-wCell" valign="top">'
						. $this->barheader($this->getLanguageService()->getLL('pageTree') . ':')
						. $temporaryTreeMountCancelNotice
						. $tree
						. '
					</td>
					<td class="c-wCell" valign="top">' . $elements . '</td>
				</tr>
			</table>
		';

		return $content;
	}

	/**
	 * @return int|NULL The UID of the page that contains the linked element, if any.
	 */
	protected function expandTheLinkedElementsParentPage() {
		$expandedPage = NULL;

		if (!isset($this->browseLinks->expandPage)) {
			$urlInfo = $this->browseLinks->curUrlInfo;

			if (!empty($urlInfo['recordTable']) && !empty($urlInfo['recordUid'])) {
				$record = BackendUtility::getRecord($urlInfo['recordTable'], $urlInfo['recordUid']);

				if ($record !== NULL) {
					$expandedPage = $record['pid'];
					$this->browseLinks->expandPage = $expandedPage;
				}
			}
		}

		return $expandedPage;
	}

	/**
	 * @param int $expandedPage
	 * @return string
	 */
	protected function renderTree($expandedPage = NULL) {
		$pageTree = $this->buildPageTree($expandedPage);
		return $pageTree->getBrowsableTree();
	}

	/**
	 * @param int $expandedPage
	 * @return PageTree
	 */
	private function buildPageTree($expandedPage) {
		$backendUser = $this->getBackendUser();

		/** @var PageTree $pageTree */
		$pageTree = GeneralUtility::makeInstance(PageTree::class);
		$pageTree->setElementBrowser($this);
		$pageTree->thisScript = $this->thisScript;
		$pageTree->ext_showPageId = (bool)$backendUser->getTSConfigVal('options.pageTree.showPageIdWithTitle');
		$pageTree->ext_showNavTitle = (bool)$backendUser->getTSConfigVal('options.pageTree.showNavTitle');
		$pageTree->addField('nav_title');

		if (
			isset($this->settings['pageTreeMountPoints.'])
			&& is_array($this->settings['pageTreeMountPoints.'])
			&& !empty($this->settings['pageTreeMountPoints.'])
		) {
			$pageTree->MOUNTS = $this->settings['pageTreeMountPoints.'];
		}

		if ($expandedPage !== NULL) {
			if (GeneralUtility::_GP('PM') === NULL ) {
				$pageTree->expandToPage($expandedPage);
			}
		}

		return $pageTree;
	}

	/**
	 * @return string
	 */
	protected function renderElements() {
		$elementBrowserRecordList = $this->buildElementBrowserRecordList();
		$this->browseLinks->setRecordList($elementBrowserRecordList);
		$tables = $this->getTables();
		$elements = $this->browseLinks->TBE_expandPage($tables);
		return $elements;
	}

	/**
	 * @return RecordListRte
	 */
	private function buildElementBrowserRecordList() {
		/** @var RecordListRte $elementBrowserRecordList */
		$elementBrowserRecordList = GeneralUtility::makeInstance(RecordListRte::class);
		$elementBrowserRecordList->setBrowseLinksObj($this->browseLinks);

		if (isset($this->settings['additionalSearchQueries.']) && is_array($this->settings['additionalSearchQueries.'])) {
			foreach ($this->settings['additionalSearchQueries.'] as $table => $searchQuery) {
				$elementBrowserRecordList->addAdditionalSearchQuery($table, $searchQuery);
			}
		}

		if (isset($this->settings['enableSearchBox'])) {
			$elementBrowserRecordList->setEnableSearchBox($this->settings['enableSearchBox']);
		}

		return $elementBrowserRecordList;
	}

	/**
	 * @return string
	 */
	private function getTables() {
		$tables = '*';

		if (isset($this->settings['listTables'])) {
			$tables = $this->settings['listTables'];
		}

		return $tables;
	}

}