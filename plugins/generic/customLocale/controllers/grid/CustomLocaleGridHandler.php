<?php

/**
 * @file controllers/grid/CustomLocaleGridHandler.php
 *
 * Copyright (c) 2016-2022 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomLocaleGridHandler
 */

namespace APP\plugins\generic\customLocale\controllers\grid;

use APP\notification\NotificationManager;
use APP\plugins\generic\customLocale\classes\CustomLocale;
use APP\plugins\generic\customLocale\controllers\grid\form\LocaleFileForm;
use APP\plugins\generic\customLocale\CustomLocalePlugin;
use Exception;
use Gettext\Generator\PoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\i18n\translation\LocaleFile;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class CustomLocaleGridHandler extends GridHandler
{
    protected LocaleFileForm $form;

    protected static CustomLocalePlugin $plugin;

    /**
     * Set the custom locale plugin.
     */
    public static function setPlugin(CustomLocalePlugin $plugin): void
    {
        static::$plugin = $plugin;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid', 'editLocale', 'updateLocale']
        );
    }

    /**
     * Edit a locale file.
     */
    public function editLocale(array $args, PKPRequest $request): JSONMessage
    {
        $this->setupTemplate($request);

        // Create and present the edit form
        $localeFileForm = new LocaleFileForm(self::$plugin, $args['locale']);
        $localeFileForm->initData();
        return new JSONMessage(true, $localeFileForm->fetch($request));
    }

    /**
     * Update the custom locale data.
     */
    public function updateLocale(array $args, PKPRequest $request): JSONMessage
    {
        ['locale' => $locale, 'changes' => $changes] = $args;

        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        if (!count($changes)) {
            $this->setupTemplate($request);
            // Create and present the edit form
            $localeFileForm = new LocaleFileForm(self::$plugin, $locale);
            $localeFileForm->initData();
            return new JSONMessage(true, $localeFileForm->fetch($request));
        }

        // save changes
        $customLocalePath = CustomLocalePlugin::getStoragePath();
        $customFilePath = "{$customLocalePath}/{$locale}/locale.po";
        $translator = CustomLocalePlugin::getTranslator($locale);
        $translations = file_exists($customFilePath)
            ? LocaleFile::loadTranslations($customFilePath)
            : Translations::create(null, $locale);
        foreach ($changes as $key => $value) {
            $value = str_replace("\r\n", "\n", $value);
            $translation = $translations->find('', $key);
            if (!strlen($value) || $translator->getSingular($key) === $value) {
                if ($translation) {
                    $translations->remove($translation);
                }
                continue;
            }
            if (!$translation) {
                $translation = Translation::create('', $key);
                $translations->add($translation);
            }
            $translation->translate($value);
        }

        $contextFileManager = CustomLocalePlugin::getContextFileManager();
        if (!is_dir($basePath = dirname($customFilePath))) {
            $contextFileManager->mkdir($basePath);
        }
        $poGenerator = new PoGenerator();
        if (!$poGenerator->generateFile($translations, $customFilePath)) {
            throw new Exception('Failed to serialize translations');
        }

        // Create success notification and close modal on save
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(false);
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc Gridhandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null): void
    {
        parent::initialize($request, $args);

        // Set the grid details.
        $this->setTitle('plugins.generic.customLocale.customLocaleFiles');
        $this->setEmptyRowText('plugins.generic.customLocale.noneCreated');

        // Columns
        $cellProvider = new CustomLocaleGridCellProvider();
        $addColumn = fn (string $id, string $title) => $this->addColumn(new GridColumn($id, $title, null, 'controllers/grid/gridCell.tpl', $cellProvider));
        $addColumn('name', 'common.description');
        $addColumn('locale', 'grid.columns.locale');
        $addColumn('action', 'common.action');
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    public function loadData($request, $filter): array
    {
        $gridDataElements = [];
        $locales = $request->getContext()->getSupportedFormLocaleNames();
        foreach (array_keys($locales) as $i => $locale) {
            $gridDataElements[] = new CustomLocale($i, $locale, $locales[$locale]);
        }

        return $gridDataElements;
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args): array
    {
        return [new PagingFeature()];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\customLocale\controllers\grid\CustomLocaleGridHandler', '\CustomLocaleGridHandler');
}
