<?php

namespace abrain\Einsatzverwaltung\Settings\Pages;

use abrain\Einsatzverwaltung\PermalinkController;
use WP_Post;

/**
 * Settings page for advanced stuff
 *
 * @package abrain\Einsatzverwaltung\Settings\Pages
 */
class Advanced extends SubPage
{
    private $permalinkOptions = array(
        PermalinkController::DEFAULT_REPORT_PERMALINK => array(
            'label' => 'WordPress-Standard'
        ),
        '%post_id%-%postname_nosuffix%' => array(
            'label' => 'Beitragsnummer und Beitragstitel ohne angehängten Zähler'
        )
    );
    /**
     * @var PermalinkController
     */
    private $permalinkController;

    /**
     * Advanced Settings page constructor.
     *
     * @param PermalinkController $permalinkController
     */
    public function __construct(PermalinkController $permalinkController)
    {
        parent::__construct('advanced', __('Advanced', 'einsatzverwaltung'));
        $this->permalinkController = $permalinkController;

        add_filter('pre_update_option_einsatzvw_rewrite_slug', array($this, 'maybeRewriteSlugChanged'), 10, 2);
    }

    public function addSettingsFields()
    {
        add_settings_field(
            'einsatzvw_permalinks_base',
            'Basis',
            array($this, 'echoFieldBase'),
            $this->settingsApiPage,
            'einsatzvw_settings_permalinks'
        );
        add_settings_field(
            'einsatzvw_permalinks_struct',
            'URL-Struktur',
            array($this, 'echoFieldUrlStructure'),
            $this->settingsApiPage,
            'einsatzvw_settings_permalinks'
        );
    }

    public function addSettingsSections()
    {
        add_settings_section(
            'einsatzvw_settings_permalinks',
            __('Permalinks', 'einsatzverwaltung'),
            function () {
                echo '<p>Eine &Auml;nderung der Permalinkstruktur hat zur Folge, dass bisherige Links auf Einsatzberichte nicht mehr funktionieren. Dem solltest du als Seitenbetreiber mit Weiterleitungen entgegenwirken.</p>';
            },
            $this->settingsApiPage
        );
    }

    /**
     * @inheritDoc
     */
    public function beforeContent()
    {
        $sampleSlug = _x('sample-incident', 'sample permalink structure', 'einsatzverwaltung');
        $fakePost = new WP_Post((object) array(
            'ID' => 1234,
            'post_name' => "$sampleSlug-3",
            'post_title' => $sampleSlug
        ));
        foreach (array_keys($this->permalinkOptions) as $permalinkStructure) {
            $this->permalinkOptions[$permalinkStructure]['code'] = $this->getSampleUrl($fakePost, $permalinkStructure);
        }
    }

    public function echoFieldBase()
    {
        global $wp_rewrite;
        echo '<fieldset>';
        $this->echoSettingsInput(
            'einsatzvw_rewrite_slug',
            sanitize_title(get_option('einsatzvw_rewrite_slug'), 'einsatzberichte')
        );
        echo '<p class="description">';
        printf(
            'Basis f&uuml;r Links zu Einsatzberichten, zum %s und zum %s.',
            sprintf('<a href="%s">%s</a>', get_post_type_archive_link('einsatz'), 'Archiv'),
            sprintf('<a href="%s">%s</a>', get_post_type_archive_feed_link('einsatz'), 'Feed')
        );
        if ($wp_rewrite->using_permalinks() === false) {
            echo '</p><p class="description">';
            printf(
                __('Note: This setting has no effect, as WordPress currently uses plain %s', 'einsatzverwaltung'),
                sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-permalink.php'),
                    __('permalinks', 'einsatzverwaltung')
                )
            );
        }
        echo '</p></fieldset>';
    }

    public function echoFieldUrlStructure()
    {
        echo '<fieldset>';
        $this->echoRadioButtons(
            'einsatz_permalink',
            $this->permalinkOptions,
            PermalinkController::DEFAULT_REPORT_PERMALINK
        );
        echo '</fieldset>';

        echo '<p class="description">';
        $sampleSlug = sanitize_title(
            _x('sample-incident', 'sample permalink structure', 'einsatzverwaltung'),
            'sample-incident'
        );
        printf(
            __('By default, WordPress uses the post name to build the URL. To ensure uniqueness across posts, the post name can have a number appended if there are other posts with the same title (e.g. %1$s, %2$s, %3$s, ...).', 'einsatzverwaltung'),
            $sampleSlug,
            "$sampleSlug-2",
            "$sampleSlug-3"
        );
        echo '</p></fieldset>';
    }

    /**
     * @inheritDoc
     */
    public function echoStaticContent()
    {
        echo '<p>Die erweiterten Einstellungen k&ouml;nnen weitreichende Konsequenzen haben und sollten entsprechend nicht leichtfertig ge&auml;ndert werden.</p>';
        return;
    }

    /**
     * @param WP_Post $post
     * @param string $permalinkStructure
     *
     * @return string
     */
    private function getSampleUrl(WP_Post $post, $permalinkStructure)
    {
        $selector = $this->permalinkController->buildSelector($post, $permalinkStructure);
        return $this->permalinkController->getPermalink($selector);
    }

    /**
     * Prüft, ob sich die Basis für die Links zu Einsatzberichten ändert und veranlasst gegebenenfalls ein Erneuern der
     * Permalinkstruktur
     *
     * @param string $newValue Der neue Wert
     * @param string $oldValue Der alte Wert
     * @return string Der zu speichernde Wert
     */
    public function maybeRewriteSlugChanged($newValue, $oldValue)
    {
        if ($newValue != $oldValue) {
            self::$options->setFlushRewriteRules(true);
        }

        return $newValue;
    }

    public function registerSettings()
    {
        register_setting(
            'einsatzvw_settings_advanced',
            'einsatzvw_rewrite_slug',
            'sanitize_title'
        );
        register_setting(
            'einsatzvw_settings_advanced',
            'einsatz_permalink',
            array('PermalinkController', 'sanitizePermalink')
        );
    }
}
