<?php
/*
 * webtrees - extended family tab (custom module)
 *
 * Copyright (C) 2025 Hermann Hartenthaler.
 * Copyright (C) 2013 Vytautas Krivickas and vytux.com.
 * Copyright (C) 2013 Nigel Osborne and kiwtrees.net.
 *
 * webtrees: online genealogy application
 * Copyright (C) 2025 webtrees development team.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * tbd
 * --------------------------  ab hier für das Release 2.2.1.2    ------------------------------------------------*
 * issues: see GitHub
 *
 * Code: Anpassungen an Bootstrap 5 (Filter-Buttons)
 * Code: collection für Familien ausprogrammieren in ExtendedFamily.php
 * Code: automatisches Kopieren in den Sammelbehälter verwerfen und stattdessen Button in Betrieb nehmen
 * Code: Fehler in Grandchildren suchen
 * Test: Konfigurationsoption "Partnerketten zählen dazu/nicht dazu"
 * Code: prüfen ob allCountUnique immer richtig berechnet wird
 * Code: siehe 2x tbd in tab.phtml #1077
 * Code: Formulierung der Zusammenfassung konsistent machen
 * Übersetzung: Satz umformulieren, da Proband=ohne Namen und ohne Geschlecht => Kurzname="ihn/sie"; Fehler: Die erweiterte von ihn/sie ... => Die erweiterte Familie von ihm/ihr ...
 * READme: alle Screenshots aktualisieren
 *
 * --------------------------  ab hier für ein Release nach 2.2.1.2    ------------------------------------------------
 * Code: neuen webtrees Validator zur Prüfung reinkommender Parameter verwenden (siehe Beispiele Magicsunday Fanchart)
 * Code: Grandchildren.php: statt großem Block wieder Unterfunktionen nutzen
 * Code: Ist die Funktion "getPedigreeValue" in ExtendedFamilySupport.php wirklich überflüssig? Dann löschen.
 * Code: weitere find... Funktionen programmieren und damit den alten Code ersetzen (in ExtendedFamilyPart.php)
 * Code: In der Zusammenfassung per countBy weitere Informationen anzeigen
 *         (Anzahl der Familien, Generationen von/bis, früheste/späteste Geburt, Anzahl m/w/u, Anzahl lebend/tod, ...) * Code: alle Family Objekte explizit als Family deklarieren
 * Code: alle array-Deklarationen mit <index,value> deklarieren
 * Code: statt array besser Collection verwenden!
 * Code: alle noch verwendeten object als Klassen definieren
 * Code: Beziehungsbezeichnungen als Label aus Vesta-Relationship oder durch eigene Funktion ergänzen?
 * Code: Funktionen getSizeThumbnailW() und getSizeThumbnailH() verbessern (siehe issue #46 von Sir Peter)
 *         Gibt es einen Zusammenhang oder sind sie unabhängig? Wie genau wirken sie sich aus?
 *         Testen, wenn im CSS-Modul nichts eingetragen ist.
 *         Option für thumbnail size? css für silhouette anpassen?
 * Code: neues Management für Updates und Information der Anwender über Neuigkeiten zu diesem Modul
 * Code: Datenbank-Schema mit Updates einführen, damit man Familienteile auch ändern und löschen kann
 * Code: restliche, verstreut vorkommenden Übersetzungen mit I18N alle nach tab.html verschieben
 * Code: Iterate-Pattern für Umgang mit groups implementieren?
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExtendedFamily;

use Hartenthaler\Webtrees\Helpers\Functions;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
//use Cissee\Webtrees\Module\ExtendedRelationships;

use function str_starts_with;   // will be added in PHP 8.0
use function explode;
use function implode;
use function count;
use function in_array;

/**
 * Class ExtendedFamilyTabModule
 */
class ExtendedFamilyTabModule extends AbstractModule
                              implements ModuleTabInterface, ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleTabTrait;
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    /**
     * list of const for module administration
     */

    // Module title
    public const CUSTOM_TITLE       = 'Extended family';

    // Module file name
    public const CUSTOM_MODULE      = 'hh_extended_family';

    // Module description
    public const CUSTOM_DESCRIPTION = 'A tab showing the extended family of an individual.';

    // Author of custom module
    public const CUSTOM_AUTHOR      = 'Hermann Hartenthaler';

    // User in GitHub
    public const CUSTOM_GITHUB_USER = 'hartenthaler';

    // GitHub repository
    public const GITHUB_REPO        = self::CUSTOM_GITHUB_USER . '/' . self::CUSTOM_MODULE;

    // Custom module website
    public const CUSTOM_WEBSITE     = 'https://github.com/' . self::GITHUB_REPO . '/';

    // Custom module version
    public const CUSTOM_VERSION     = '2.2.1.1';
    public const CUSTOM_LAST        = 'https://github.com/' . self::CUSTOM_GITHUB_USER . '/' .
                                                            self::CUSTOM_MODULE . '/raw/main/latest-version.txt';
    // Versionsprüfung von hh_metasearch übernehmen ??? oder ganz anders?

    /**
     * Constructor.  The constructor is called on *all* modules, even ones that are disabled.
     * This is a good place to load business logic ("services").  Type-hint the parameters and
     * they will be injected automatically.
     */
    public function __construct()
    {
        // NOTE:  If your module is dependent on any of the business logic ("services"),
        // then you would type-hint them in the constructor and let webtrees inject them
        // for you.  However, we can't use dependency injection on anonymous classes like
        // this one. For an example of this, see the example-server-configuration module.

        // use helper function in order to work with webtrees versions 2.1 and 2.2
        $response_factory = Functions::getFromContainer(ResponseFactoryInterface::class);
    }

    /**
     * find members of extended family parts
     *
     * @param Individual $proband
     * @return object
     */
    private function getExtendedFamily(Individual $proband): object
    {
        return new ExtendedFamily($proband, $this->buildConfig($proband));
    }

    /**
     * check in efficient way if there is at least one person in one of the selected extended family parts
     * (used to decide if tab has to be grayed out)
     *
     * @param Individual $proband
     * @return bool
     */
    private function personExistsInExtendedFamily(Individual $proband): bool
    {
        return (new ExtendedFamilyPersonExists($proband, $this->buildConfig($proband)))->found();
    }
     
    /**
     * get configuration information
     *
     * @param Individual $proband
     * @return object
     */
    private function buildConfig(Individual $proband): object
    {
        $configObj = (object)[];
        $configObj->showFilterOptions           = $this->showFilterOptions();
        $configObj->filterOptions               = $this->showFilterOptions() ? ExtendedFamilySupport::getFilterOptions(): ['all'];
        $configObj->showSummary                 = $this->showSummary();
        $configObj->showEmptyBlock              = $this->showEmptyBlock();
        $configObj->countPartnerChainsToTotal   = $this->countPartnerChainsToTotal();
        $configObj->showShortName               = $this->showShortName();
        $configObj->showLabels                  = $this->showLabels();
        $configObj->useCompactDesign            = $this->useCompactDesign();
        $configObj->useClippingsCart            = $this->useClippingsCart();
        $configObj->shownFamilyParts            = $this->getShownFamilyParts();
        $configObj->showParameters              = $this->showParameters();
        $configObj->familyPartParameters        = ExtendedFamilySupport::getFamilyPartParameters();
        $configObj->showThumbnail               = $this->showThumbnail($proband->tree());
        $configObj->sizeThumbnailW              = $this->getSizeThumbnailW();
        $configObj->sizeThumbnailH              = $this->getSizeThumbnailH();
        //$configObj->name = $this->name();     // nötig, falls Vesta-Module doch genutzt werden sollten
        //(unklar wie diese Information ins Modul ExtendedFamilyPart.php transferiert werden soll)
        return $configObj;
    }

    /**
     * size for thumbnails W
     *
     * @return int
     */
    private function getSizeThumbnailW(): int
    {
        return 66;
    }

    /**
     * size for thumbnails H
     *
     * @return int
     */
    private function getSizeThumbnailH(): int
    {
        return 100;
    }

    /**
     * dependency check if Vesta modules are available (needed for relationship name)
     *
     * @param bool $showErrorMessage
     * @return bool
     */
    public static function VestaModulesAvailable(bool $showErrorMessage): bool
    {
        $vesta = class_exists("Cissee\WebtreesExt\AbstractModule", true);
        if (!$vesta && $showErrorMessage) {
            FlashMessages::addMessage("Missing dependency - Make sure to install all Vesta modules!");
        }
        return $vesta;
    }

    /**
     * generate list of other preferences
     * (control panel options beside the options related to the extended family parts itself)
     *
     * @return array<int,string>
     */
    private function listOfOtherPreferences(): array
    {
        return [
            'show_filter_options',
            'show_empty_block',
            'show_short_name',
            'show_labels',
            'show_parameters',
            'use_compact_design',
            'show_summary',
            'count_partner_chains',
            'use_clippings_cart',
        ];
    }

    /**
     * view module settings in control panel
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts' . DIRECTORY_SEPARATOR . 'administration';
        $response = [];
        
        $preferences = $this->listOfOtherPreferences();
        foreach ($preferences as $preference) {
            $response[$preference] = $this->getPreference($preference);
        }

        $response['efps']           = $this->getShownFamilyParts();
        $response['title']          = $this->title();
        $response['description']    = $this->description();
        $response['uses_sorting']   = true;

        return $this->viewResponse($this->name() . '::' . 'settings', $response);
    }

    /**
     * save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        // tbd: use Validator::parsedBody($request)->string|boolean|...('xxx', ''|true|...);
        $params = (array) $request->getParsedBody();

        // save the received settings to the user preferences
        if ($params['save'] === '1') {
            $this->postAdminActionOther($params);
            $this->postAdminActionEfp($params);
            FlashMessages::addMessage(I18N::translate('The preferences for the module “%s” have been updated.',
                $this->title()), 'success');
        }
        return redirect($this->getConfigLink());
    }

    /**
     * save the user preferences for all parameters
     * that are not explicitly related to the extended family parts in the database
     *
     * @param array $params configuration parameters
     */
    private function postAdminActionOther(array $params)
    {
        $preferences = $this->listOfOtherPreferences();
        foreach ($preferences as $preference) {
            $this->setPreference($preference, $params[$preference]);
        }
    }

    /**
     * save the user preferences for all parameters related to the extended family parts in the database
     *
     * @param array $params configuration parameters
     */
    private function postAdminActionEfp(array $params)
    {
        $order = implode(",", $params['order']);
        $this->setPreference('order', $order);
        foreach (ExtendedFamilySupport::listFamilyParts() as $efp) {
            $this->setPreference('status-' . $efp, '0');
        }
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'status-')) {
                $this->setPreference($key, $value);
            }
        }
    }

    /**
     * parts of extended family which should be shown (order and enabled/disabled)
     * set default values in case the settings are not stored in the database yet
     *
     * @return array<string,object> of ordered objects with translated name and status (enabled/disabled)
     */
    private function getShownFamilyParts(): array
    {
        $listFamilyParts = ExtendedFamilySupport::listFamilyParts();
        $orderDefault = implode(',', $listFamilyParts);
        $order = explode(',', $this->getPreference('order', $orderDefault));

        if (count($listFamilyParts) > count($order)) {
            $this->addFamilyParts($listFamilyParts, $order);
        }
        
        $shownParts = [];
        foreach ($order as $efp) {
            $efpObj = (object)[];
            $efpObj->name       = ExtendedFamilySupport::translateFamilyPart($efp);
            $efpObj->generation = ExtendedFamilySupport::formatGeneration($efp);
            $efpObj->enabled    = $this->getPreference('status-' . $efp, 'on');
            $shownParts[$efp]   = $efpObj;
        }
        return $shownParts;
    }

    /**
     * add parts of extended family, which are newly defined
     * tbd: it is not possible to delete family parts, only add new ones
     *
     * @param array $listFamilyParts list of extended family parts defined by this module
     * @param array $order list of ordered family parts out of parameters
     */
    private function addFamilyParts(array $listFamilyParts, array &$order)
    {

        foreach ($listFamilyParts as $familyPart) {
            if (!in_array($familyPart, $order)) {
                $order[] = $familyPart;                 // add new parts at the end of the list
            }
        }
    }

    /**
     * should filter options be shown (user can filter by gender or alive/dead)
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function showFilterOptions(): bool
    {
        return ($this->getPreference('show_filter_options', '0') == '0');
    }

    /**
     * how should empty parts of the extended family be presented
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function showSummary(): bool
    {
        return ($this->getPreference('show_summary', '0') == '0');
    }

    /**
     * how should empty parts of the extended family be presented
     * set default values in case the settings are not stored in the database yet
     *
     * @return string
     */
    private function showEmptyBlock(): string
    {
        return $this->getPreference('show_empty_block', '0');
    }

    /**
     * how should empty parts of the extended family be presented
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function countPartnerChainsToTotal(): bool
    {
        return ($this->getPreference('count_partner_chains', '0') == '0');
    }

    /**
     * should a short name of proband be shown
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function showShortName(): bool
    {
        return ($this->getPreference('show_short_name', '0') == '0');
    }

    /**
     * should a label be shown
     * labels are shown for special situations like:
     * person: adopted person, stillborn
     * siblings and children: adopted or foster child, twin
     *
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function showLabels(): bool
    {
        return ($this->getPreference('show_labels', '0') == '0');
    }

    /**
     * should parameters for each extended family part be shown (like generation shift and coefficient of relationship)
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function showParameters(): bool
    {
        return ($this->getPreference('show_parameters', '0') == '0');
    }

    /**
     * use compact design for individual blocks or show additional information (photo, birth and death information)
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function useCompactDesign(): bool
    {
        return ($this->getPreference('use_compact_design', '0') == '0');
    }

    /**
     * get preference in this tree to show thumbnails
     * @param object $tree
     *
     * @return bool
     */
    private function isTreePreferenceShowingThumbnails(object $tree): bool
    {
        return ($tree->getPreference('SHOW_HIGHLIGHT_IMAGES') == '1');
    }

    /**
     * show thumbnail if compact design is not selected and if global preference allows seeing thumbnails
     *
     * @param object $tree
     * @return bool
     */
    private function showThumbnail(object $tree): bool
    {
        return (!$this->useCompactDesign() && $this->isTreePreferenceShowingThumbnails($tree));
    }

    /**
     * use the function to add individuals and families to the clippings cart
     * set default values in case the settings are not stored in the database yet
     *
     * @return bool
     */
    private function useClippingsCart(): bool
    {
        return ($this->getPreference('use_clippings_cart', '0') == '0');
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return /* I18N: Name of a module/tab on the individual page. */ I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * {@inheritDoc}
     *
     * @see AbstractModule::description
     */
    public function description(): string
    {
        return /* I18N: Description of this module */ I18N::translate(self::CUSTOM_DESCRIPTION);
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * Where to get support for this module?  Perhaps a GitHub repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }
    
    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }

    /**
     * The default position for this tab can be changed in the control panel.
     *
     * @return int
     */
    public function defaultTabOrder(): int
    {
        return 10;
    }

    /**
     * Is this tab empty? If so, we don't always need to display it.
     *
     * @param Individual $individual
     * @return bool
     */
    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    /**
     * A greyed out tab has no actual content, but perhaps have options to create content.
     *
     * @param Individual $individual
     * @return bool
     */
    public function isGrayedOut(Individual $individual): bool
    {
        return !$this->personExistsInExtendedFamily($individual);
    }

    /**
     * Where are the CCS specifications for this module stored?
     *
     * @return ResponseInterface
     *
     * @throws \JsonException
     */
    public function getCssAction() : ResponseInterface
    {
        return response(
            file_get_contents($this->resourcesFolder() . 'css' . DIRECTORY_SEPARATOR . self::CUSTOM_MODULE . '.css'),
            200,
            ['content-type' => 'text/css']
        );
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual): string
    {
        /*return view($this->name() . '::test.blade', ['title'=>'Laravel Blade Example']);*/
        return view($this->name() . '::' . 'tab',
            [
            'extfam_obj'            => $this->getExtendedFamily($individual),
            'extended_family_css'   => route('module', ['module' => $this->name(), 'action' => 'Css']),
            ]);
    }

    /** {@inheritdoc} */
    public function canLoadAjax(): bool
    {
        return false;
    }

    /**
     * bootstrap
     *
     * Here is also a good place to register any views (templates) used by the module.
     * This command allows the module to use: view($this->name() . '::', 'fish')
     * to access the file ./resources/views/fish.phtml
     */
    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
    
    /**
     * additional translations
     *
     * @param string $language
     *
     * @return array<string, string>
     */
    public function customTranslations(string $language): array
    {
        // Here we are using an array for translations.
        // If you had .MO files, you could use them with: return (new Translation('path/to/file.mo'))->asArray();

        require_once(__DIR__ . '/resources/lang/ExtendedFamilyTranslations.php');

        switch ($language) {
            case 'ca':
            case 'ca-ES':
                $customTranslation = ExtendedFamilyTranslations::catalanTranslations();
                break;
            case 'cs':
                $customTranslation = ExtendedFamilyTranslations::czechTranslations();
                break;
            case 'de':
                $customTranslation = ExtendedFamilyTranslations::germanTranslations();
                break;
            case 'es':
                $customTranslation = ExtendedFamilyTranslations::spanishTranslations();
                break;
            case 'fr':
            case 'fr-CA':
                $customTranslation = ExtendedFamilyTranslations::frenchTranslations();
                break;
            case 'hi':
                $customTranslation = ExtendedFamilyTranslations::hindiTranslations();
                break;
            case 'it':
                $customTranslation = ExtendedFamilyTranslations::italianTranslations();           // tbd
                break;
            case 'nb':
                $customTranslation = ExtendedFamilyTranslations::norwegianBokmålTranslations();
                break;
            case 'nl':
                $customTranslation = ExtendedFamilyTranslations::dutchTranslations();
                break;
            case 'ru':
                $customTranslation = ExtendedFamilyTranslations::russianTranslations();
                break;
            case 'sk':
                $customTranslation = ExtendedFamilyTranslations::slovakTranslations();
                break;
            case 'uk':
                $customTranslation = ExtendedFamilyTranslations::ukrainianTranslations();
                break;
            case 'vi':
                $customTranslation = ExtendedFamilyTranslations::vietnameseTranslations();
                break;
            case 'zh-Hans':
                $customTranslation = ExtendedFamilyTranslations::chineseSimplifiedTranslations();
                break;
            case 'zh-Hant':
                $customTranslation = ExtendedFamilyTranslations::chineseTraditionalTranslations();
                break;
            default:
                $customTranslation = [];
                break;
        }
        return $customTranslation;
    }
}
return new ExtendedFamilyTabModule;
