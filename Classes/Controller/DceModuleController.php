<?php
namespace T3\Dce\Controller;

/*  | This extension is made with love for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2012-2019 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\Dce\Components\FlexformToTcaMapper\Mapper;
use T3\Dce\Domain\Model\Dce;
use T3\Dce\Domain\Repository\DceRepository;
use T3\Dce\Utility\File;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * DCE Module Controller
 * Provides the backend DCE module, for faster and easier access to DCEs.
 */
class DceModuleController extends ActionController
{
    /**
     * @var DceRepository
     */
    protected $dceRepository;

    /**
     * Initialize Action
     *
     * @return void
     */
    public function initializeAction() : void
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->dceRepository = $objectManager->get(DceRepository::class);
    }

    /**
     * Index Action
     *
     * @return void
     */
    public function indexAction() : void
    {
        $this->view->assign('dces', $this->dceRepository->findAllAndStatics(true));
    }

    /**
     * @param Dce $dce
     * @param bool $perform
     * @return void
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function updateTcaMappingsAction(Dce $dce, $perform = false) : void
    {
        $contentElements = $this->dceRepository->findContentElementsBasedOnDce($dce);
        $this->view->assign('contentElements', $contentElements);
        $this->view->assign('dce', $dce);
        if ($perform) {
            foreach ($contentElements as $contentElement) {
                Mapper::saveFlexformValuesToTca(
                    $contentElement,
                    $contentElement['pi_flexform']
                );
            }
            $this->view->assign('perform', true);
        }
    }

    /**
     * Clears Caches Action
     *
     * @return void
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function clearCachesAction() : void
    {
        /** @var DataHandler $dataHandler */
        $dataHandler = $this->objectManager->get(DataHandler::class);
        $dataHandler->start([], []);
        $dataHandler->clear_cacheCmd('system');
        $dataHandler->clear_cacheCmd('pages');
        $translateKey = 'LLL:EXT:dce/Resources/Private/Language/locallang_mod.xml:';
        $this->addFlashMessage(
            LocalizationUtility::translate($translateKey . 'clearCachesFlashMessage', 'dce'),
            LocalizationUtility::translate($translateKey . 'clearCaches', 'dce')
        );
        $this->redirect('index');
    }

    /**
     * Hall of fame Action
     *
     * @return void
     */
    public function hallOfFameAction() : void
    {
        $donators = File::openJsonFile('EXT:dce/Resources/Private/Data/Donators.json');
        shuffle($donators);
        $this->view->assign('donators', $donators);
    }
}
