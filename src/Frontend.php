<?php
namespace abrain\Einsatzverwaltung;

use abrain\Einsatzverwaltung\Frontend\ReportList;
use abrain\Einsatzverwaltung\Model\IncidentReport;
use abrain\Einsatzverwaltung\Util\Formatter;
use WP_Post;
use WP_Query;

/**
 * Generiert alle Inhalte für das Frontend, mit Ausnahme der Shortcodes und des Widgets
 */
class Frontend
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var Options
     */
    private $options;

    /**
     * Constructor
     *
     * @param Options $options
     * @param Formatter $formatter
     */
    public function __construct($options, $formatter)
    {
        $this->formatter = $formatter;
        $this->options = $options;
        $this->addHooks();
    }

    private function addHooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueueStyleAndScripts'));
        if (!(
            is_array($_REQUEST) &&
            array_key_exists('plugin', $_REQUEST) && $_REQUEST['plugin'] == 'all-in-one-event-calendar' &&
            array_key_exists('action', $_REQUEST) && $_REQUEST['action'] == 'export_events'
        )) {
            add_filter('the_content', array($this, 'renderContent'), 9);
        }
        add_filter('the_excerpt', array($this, 'filterEinsatzExcerpt'));
        add_filter('the_excerpt_rss', array($this, 'filterEinsatzExcerpt'));
        add_filter('the_excerpt_embed', array($this, 'filterEinsatzExcerpt'));
        add_action('pre_get_posts', array($this, 'addReportsToQuery'));
    }

    /**
     * Bindet CSS für das Frontend ein
     */
    public function enqueueStyleAndScripts()
    {
        wp_enqueue_style(
            'font-awesome',
            Core::$pluginUrl . 'font-awesome/css/font-awesome.min.css',
            false,
            '4.7.0'
        );
        wp_enqueue_style(
            'einsatzverwaltung-frontend',
            Core::$styleUrl . 'style-frontend.css',
            array(),
            Core::VERSION
        );
        wp_add_inline_style('einsatzverwaltung-frontend', ReportList::getDynamicCss());

        if ($this->options->isGMapActivate()) {
            wp_enqueue_script('einsatzvw_GoogleMap');
            wp_enqueue_script(
                'einsatzverwaltung-gmap',
                $this->core->scriptUrl . 'einsatzverwaltung-gmaps.js'
            );
        }
    }

    /**
     * Erzeugt den Kopf eines Einsatzberichts
     *
     * @param WP_Post $post Das Post-Objekt
     * @param bool $mayContainLinks True, wenn Links generiert werden dürfen
     * @param bool $showArchiveLinks Bestimmt, ob Links zu Archivseiten generiert werden dürfen
     *
     * @return string Auflistung der Einsatzdetails
     */
    public function getEinsatzberichtHeader($post, $mayContainLinks = true, $showArchiveLinks = true)
    {
        if (get_post_type($post) == "einsatz") {
            $report = new IncidentReport($post);

            $typesOfAlerting = $this->formatter->getTypesOfAlerting($report);

            $duration = $report->getDuration();
            $durationString = ($duration === false ? '' : $this->formatter->getDurationString($duration));

            $showEinsatzartArchiveLink = $showArchiveLinks && $this->options->isShowEinsatzartArchive();
            $art = $this->formatter->getTypeOfIncident($report, $mayContainLinks, $showEinsatzartArchiveLink);

            if ($report->isFalseAlarm()) {
                $art = (empty($art) ? 'Fehlalarm' : $art.' (Fehlalarm)');
            }

            $einsatzort = $report->getLocation();
            $einsatzleiter = $report->getIncidentCommander();
            $mannschaft = $report->getWorkforce();

            $vehicles = $this->formatter->getVehicles($report, $mayContainLinks, $showArchiveLinks);
            $additionalForces = $this->formatter->getAdditionalForces($report, $mayContainLinks, $showArchiveLinks);

            $timeOfAlerting = $report->getTimeOfAlerting();
            $datumsformat = $this->options->getDateFormat();
            $zeitformat = $this->options->getTimeFormat();
            $einsatz_datum = ($timeOfAlerting ? date_i18n($datumsformat, $timeOfAlerting->getTimestamp()) : '-');
            $einsatz_zeit = ($timeOfAlerting ? date_i18n($zeitformat, $timeOfAlerting->getTimestamp()).' Uhr' : '-');

            $headerstring = "<strong>Datum:</strong> ".$einsatz_datum."&nbsp;<br>";
            $headerstring .= "<strong>Alarmzeit:</strong> ".$einsatz_zeit."&nbsp;<br>";
            $headerstring .= $this->getDetailString('Alarmierungsart:', $typesOfAlerting);
            $headerstring .= $this->getDetailString('Dauer:', $durationString);
            $headerstring .= $this->getDetailString('Art:', $art);
            $headerstring .= $this->getDetailString('Einsatzort:', $einsatzort);
            $headerstring .= $this->getDetailString('Einsatzleiter:', $einsatzleiter);
            $headerstring .= $this->getDetailString('Mannschaftsst&auml;rke:', $mannschaft);
            $headerstring .= $this->getDetailString('Fahrzeuge:', $vehicles);
            $headerstring .= $this->getDetailString('Weitere Kr&auml;fte:', $additionalForces);

            return "<p>$headerstring</p>";
        }
        return "";
    }

    /**
     * Erzeugt eine Google Map des Einsatzortes
     *
     * @return string Code zum Erzeugen der Google-Map mit Markierten einsatzort
     */
    public function getEinsatzberichtMap($post)
    {
        $report = new IncidentReport($post);
        $location = $report->getGmapsLocation();
        if ($this->options->isGMapActivate() && $location) {
            $latLon = explode(",", $location);

            $mapstring = "<style>#map-canvas {height: 300px; position: relative; overflow: hidden;";
            $mapstring .= " transform: translateZ(0px); background-color: rgb(229, 227, 223);}</style>";
            $mapstring .= "<div class='einsatzliste-map'>";
            $mapstring .= "<div id='map-canvas'></div>";
            $mapstring .= "</div>";
            $mapstring .= "<div style='clear:both'></div>";
            $mapstring .= "<script>";
            $mapstring .= "google.maps.event.addDomListener(window, 'load', initializeMap(";
            $mapstring .= $latLon[0] . ", " . $latLon[1] . "));";
            $mapstring .= "addMarker( " . $latLon[0] . ", " . $latLon[1] . ", '" . $report->getLocation() . "', true )";
            $mapstring .= "</script>";

            return "<p>$mapstring</p>";
        }
    }

    /**
     * Erzeugt eine Zeile für die Einsatzdetails
     *
     * @param string $title Bezeichnung des Einsatzdetails
     * @param string $value Wert des Einsatzdetails
     * @param bool $newline Zeilenumbruch hinzufügen
     *
     * @return string Formatiertes Einsatzdetail
     */
    private function getDetailString($title, $value, $newline = true)
    {
        if ($this->options->isHideEmptyDetails() && (!isset($value) || $value === '')) {
            return '';
        }

        return '<strong>'.$title.'</strong> '.$value.($newline ? '&nbsp;<br>' : '&nbsp;');
    }


    /**
     * Beim Aufrufen eines Einsatzberichts vor den Text den Kopf mit den Details einbauen
     *
     * @param string $content Der Beitragstext des Einsatzberichts
     *
     * @return string Mit Einsatzdetails angereicherter Beitragstext
     */
    public function renderContent($content)
    {
        global $post;

        // Wenn Beiträge durch ein Passwort geschützt sind, werden auch keine Einsatzdetails preisgegeben
        if (post_password_required()) {
            return $content;
        }

        if ($this->useReportTemplate()) {
            $template = get_option('einsatzverwaltung_reporttemplate', '');

            if (empty($template)) {
                return $content;
            }

            $templateWithData = $this->formatter->formatIncidentData($template, array(), $post, 'post');
            $templateWithContent = str_replace('%content%', $content, $templateWithData);
            return stripslashes(wp_filter_post_kses(addslashes($templateWithContent)));
        }

        if (!is_singular('einsatz')) {
            return $content;
        }

        // Fallback auf das klassische Layout
        $header = $this->getEinsatzberichtHeader($post, true, true);
        $content = $this->prepareContent($content);
        $map = "";
        if ($this->options->isGMapActivate()) {
            $map = $this->getEinsatzberichtMap($post);
        }

        return $header . $map . '<hr>' . $content;
    }

    /**
     * Entscheidet, ob für die Ausgabe des Einsatzberichts das Template verwendet wird oder nicht
     *
     * @return bool
     */
    private function useReportTemplate()
    {
        $useTemplate = get_option('einsatzverwaltung_use_reporttemplate', 'no');

        if ($useTemplate === 'no') {
            return false;
        }

        if ($useTemplate === 'singular' && is_singular('einsatz') && is_main_query() && in_the_loop()) {
            return true;
        }

        if ($useTemplate === 'loops' && get_post_type() === 'einsatz' && is_main_query() && in_the_loop()) {
            return true;
        }

        if ($useTemplate === 'everywhere' && get_post_type() === 'einsatz') {
            return true;
        }

        return false;
    }

    /**
     * Bereitet den Beitragstext auf
     *
     * @param string $content Der Beitragstext des Einsatzberichts
     *
     * @return string Der Beitragstext mit einer vorangestellten Überschrift. Wenn der Beitragstext leer ist, wird ein
     * Ersatztext zurückgegeben
     */
    private function prepareContent($content)
    {
        return empty($content) ? '<p>Kein Einsatzbericht vorhanden</p>' : '<h3>Einsatzbericht:</h3>' . $content;
    }


    /**
     * Stellt den Auszug zur Verfügung, im Fall von Einsatzberichten wird
     * hier wahlweise der Berichtstext, Einsatzdetails oder beides zurückgegeben
     *
     * @param string $excerpt Filterparameter, wird bei Einsatzberichten nicht beachtet, bei anderen Beitragstypen
     * unverändert verwendet
     *
     * @return string Der Auszug
     */
    public function filterEinsatzExcerpt($excerpt)
    {
        global $post;
        if (get_post_type() !== 'einsatz') {
            return $excerpt;
        }

        if (get_option('einsatzverwaltung_use_excerpttemplate') !== '1') {
            return $excerpt;
        }

        $template = get_option('einsatzverwaltung_excerpttemplate', '');

        if (empty($template)) {
            return $excerpt;
        }

        $formatted = $this->formatter->formatIncidentData($template, array(), $post, is_feed() ? 'feed' : 'post');
        return stripslashes(wp_filter_post_kses(addslashes($formatted)));
    }

    /**
     * Gibt Einsatzberichte ggf. auch zwischen den 'normalen' Blogbeiträgen aus
     *
     * @param WP_Query $query
     */
    public function addReportsToQuery($query)
    {
        // Nur, wenn Filter erlaubt sind, soll weitergemacht werden
        if (!empty($query->query_vars['suppress_filters'])) {
            return;
        }

        // Im Adminbereich wird nicht herumgepfuscht!
        if (is_admin()) {
            return;
        }

        // Bei Abfragen einzelner Posts gibt es auch nichts zu ändern
        if ($query->is_singular()) {
            return;
        }

        $categoryId = $this->options->getEinsatzberichteCategory();
        if ($this->options->isShowReportsInLoop() || $query->is_category($categoryId)) {
            // Einsatzberichte mit abfragen
            if (isset($query->query_vars['post_type'])) {
                $postTypes = (array) $query->query_vars['post_type'];
            } else {
                $postTypes = array('post');
            }

            // Einsatzberichte nur zusammen mit Beiträgen abfragen
            if (!in_array('post', $postTypes)) {
                return;
            }

            $postTypes[] = 'einsatz';
            $query->set('post_type', $postTypes);

            if ($this->options->isOnlySpecialInLoop()) {
                // Nur als besonders markierte Einsatzberichte abfragen
                $metaQuery = $query->get('meta_query');
                if (empty($metaQuery)) {
                    $metaQuery = array();
                }
                $metaQuery['relation'] = 'OR';
                $metaQuery[] = array(
                    'key' => 'einsatz_special',
                    'value' => '1'
                );
                // normale Beiträge haben diesen Metaeintrag nicht, sollen aber trotzdem angezeigt werden
                $metaQuery[] = array(
                    'key' => 'einsatz_special',
                    'value' => '1',
                    'compare' => 'NOT EXISTS'
                );
                $query->set('meta_query', $metaQuery);
            }
        }
    }
}
