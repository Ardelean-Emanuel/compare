<?php

defined('MOODLE_INTERNAL') || die();

// \todo\future Remove. Atm StudiVemint content requires the availability of jquery. Content should
// manage its own dependencies via loading per AMD in future.
function theme_orca_page_init(moodle_page $page)
{
    global $CFG;

    /* \todo\think This gets loaded for every page but is needed for studivemnit only. 
     * Do something like (?):
     *  
     * thiscontent = \contenthub\content::from_id(id_extracted_from_page);
     * vemint = \contenthub\content::from_idnumber('vemint');
     * if (vemint.contains(thiscontent))
     *     load the stuff below...
     */

    $page->requires->jquery();
    $page->requires->js(new moodle_url($CFG->wwwroot . '/theme/orca/javascript/studivemint_jquery.tooltipster.min.js'));

    // Inject mathjax.
    if (!isset($CFG->additionalhtmlhead)) {
        $CFG->additionalhtmlhead = '';
    }
    $CFG->additionalhtmlhead .= <<<HTML
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/mathjax@2.7.8/MathJax.js">
    MathJax.Hub.Config({
        config: ["Accessible.js", "Safe.js"],
        errorSettings: { message: ["!"] },
        skipStartupTypeset: true,
        messageStyle: "none",
        tex2jax: {inlineMath: [['$','$'], ['\\\\(','\\\\)']]}
    });
</script>
HTML;
}

function orca_sidebar()
{
    global $PAGE, $DB, $COURSE, $CFG;
    $infomatr = false;
    $wissenstest = false;
    $mathe_id = 1;
    $sput_id = 8;
    $content = '';
    $cat_id = $COURSE->category;

    // Hilfsvariablen, mit denen man Moodle-Bereiche ansprechen kann (SpuT/Mathe, Test/Kurs):
    $coursecateg = $DB->get_record_sql('SELECT * FROM {course_categories} WHERE idnumber = "mathcourse"');

    // Alle Mathe-Kurse
    $matheBereich =   $cat_id    ==    $coursecateg->id;
    $mathtest = $DB->get_record_sql('SELECT * FROM {course} WHERE idnumber = "mathtest"');
    // Spezialfall: Mathe-Kurs mit der ID 33 ist Test
    $matheTest                 =    $COURSE->id    == $mathtest->id;

    // Alle SpuT-Kurse
    $sputBereich            =    $cat_id == $sput_id
        | $DB->record_exists("course_categories", array("id" => $cat_id, "parent" => $sput_id))
        | $COURSE->category == $sput_id | $DB->record_exists("course_categories", array("id" => $COURSE->category, "parent" => $sput_id));

    // Spezialfall: SpuT-Kurs mit der ID 34 ist Test
    $sputTest                 =    $cat_id    == 34 | $COURSE->category == 34;

    // Alle Wissenstest-Seiten
    $wissenstest             =    $matheTest | $sputTest;

    // Spezialfall: Alle Seiten, die keine Kurs- oder Testseiten sind == Infomaterial, z.B. für die Navi aktiv
    $infomatr                 =     !($matheBereich | $sputBereich | $wissenstest);


    // //Spezialfall: Anzeige einer Umfrage nur in der Navigationsansicht in spezieller Form
    // $umfrage =   $cat_id == 11 | $cat_id == 13 | $cat_id == 6 | $cat_id == 12 | $cat_id == 9 | $cat_id == 10 | $cat_id == 14 | $cat_id == 5 | $cat_id == 7 | $cat_id == 8;
    $class = '';

    if (strpos($_SERVER["REQUEST_URI"], 'view.php') !== false || strpos($_SERVER["REQUEST_URI"], 'attempt.php') !== false) {
        $class = 'hidden-xs hidden-sm hidden';
    }
    //    if($umfrage){
    //     $class .= ' umfrage-block side-tab style-sprachundtext';
    //    }else{
    $class .= ' hidden-xs hidden-sm hidden';
    //  }

    if (strpos($_SERVER["REQUEST_URI"], 'view.php') !== false || strpos($_SERVER["REQUEST_URI"], 'attempt.php') !== false) {
        $id = '';
    } else if ($sputBereich == true) {
        $id = 'umfrage-block';
    }

    //Jquery aktvieren
    $PAGE->requires->jquery();
    //Einbundung des JS für dir Tooltips von Studivemint
    if ($matheBereich) {
        $PAGE->requires->js('/theme/orca/javascript/jquery.tooltipster.min.js');
    }
    //Fancybox für das Video auf der Mathematik-Unterseite
    //TODO: Wieso wurde die global geladen?    
    $PAGE->requires->js('/theme/orca/javascript/jquery.fancybox.min.js');

    if ($matheTest)
        $PAGE->requires->js('/theme/orca/javascript/am-videoplayer-standalone.min.js');

    $PAGE->requires->js('/theme/orca/javascript/orca.js');

    $PAGE->requires->js('/theme/orca/javascript/shariff.min.js');

    if (!$matheBereich) {
        $PAGE->requires->js('/theme/orca/javascript/tooltip.js');
    } else {
        $PAGE->requires->js('/theme/orca/javascript/mathebereich.js');
    }
    if ($matheBereich) {
        $content = ' <section>
    <div class="container not-visible ' . $class . ' " id="' . $id . '"';
        $content .= '<div class="row equal-height clearfix m-0">';
        $content .= '<div class="tutor-box-right">
    <!-- Klasse zuweisen, damit per css das richtige Headerbild eingesetzt werden kann -->
     <div class="col-sm-6 tutor-bild matheUmfrage">
         <div class="bildnachweis">
         © Tierney/Adobe Stock
         </div>
     </div>
     <div class="col-sm-6 tutor-text">
         <h2>Ihre Meinung ist gefragt! </h2>
         <p>Zur Verbesserung unseres Angebots bitten wir Sie, sich kurz Zeit für eine Umfrage zu nehmen. <br>
             <a class="umfrage-link external" href="https://evastud.uv.ruhr-uni-bochum.de/evasys/online.php?p=Studiport" title="Zur Umfrage" target="_blank">Zur Umfrage</a></p>

     </div>
 </div>
 <div class="umfrage-infoblock">
     <div class="sidescroll-button-umfrage">
         <i class="fa fa-chevron-left" aria-hidden="true"></i>
         </div>
             <h3>Umfrage</h3>
         </div>
     </div>
 </div></div></div>';
    }
    if (strpos($_SERVER["REQUEST_URI"], 'view.php') !== false || strpos($_SERVER["REQUEST_URI"], 'attempt.php') !== false) {
        $class = 'hidden-xs hidden-sm';
    }
    if ($matheBereich) {
        $class = 'side-tab style-mathe';
    } else if ($matheTest == true) {
        $class = 'side-tab style-sprachundtext';
    } else if ($sputBereich == true) {
        $class =  'side-tab style-sprachundtext';
    }

    if ($matheBereich) {
        $content .= '<div class="container tutor-block ' . $class . '">';


        $content .= '  <div class="row equal-height m-0 clearfix">
            <div class="tutor-infoblock">
                <div class="sidescroll-button">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i>
                </div>';
    }
    if ($matheBereich) {
        $content .= '<h3>Mathe-Support & Chat</h3>';
    } else if ($sputBereich) {
        $content .= '<h3>Schreibberatungen in NRW</h3>';
    }

    if ($matheTest) {
        $class = 'matheTest';
    } else if ($matheBereich) {
        $class = 'matheKurse';
    } else if ($sputTest) {
        $class = 'sputTest';
    } else if ($sputBereich) {
        $class = 'sputKurse';
    }
    if ($matheBereich) {
        $content .= '</div>
    <div class="tutor-box-right">
       <!-- Klasse zuweisen, damit per css das richtige Headerbild eingesetzt werden kann -->
        ';
    }

    if ($matheBereich) {
        $content .= ' 
                <div class="col-sm-12 tutor-text">
                    <!-- Headertext: Test Mathematik -->';
    }
    //  if ($matheTest){
    // $content .=' <h2>Sind Sie gerüstet für <span class="wint-color-muted" style="cursor: help;" title="Wirtschaftswissenschaften, Informatik, Naturwissenschaften, Technik">WINT</span>?</h2>
    //         <h3>Prüfen Sie Ihre mathematischen Kenntnisse! </h3>
    //         <p>Eine <b>Ergebnisrückmeldung</b> erhalten Sie nach jedem Subtest. Wenn Sie alle Wissensbereiche (außer Stochastik) bearbeitet haben, erhalten Sie eine <b>WINT-Bescheinigung</b> zum Download.</p><p>Zur Vertiefung des Lernstoffs stehen Ihnen zwei <a href="/moodle/course/index.php?categoryid=3" class="wint-color" title="Zur Mathematik-Kursübersicht">Mathematik-Kurse</a> zur Verfügung.</p>';

    //      } else 
    if ($matheBereich) {
        $content .= ' <h2>Mathe-Heldesk</h2>
        <span class="Bei-inhaltlichen-Fragen-kontaktieren-Sie-uns">
        Kontaktieren Sie bei inhaltlichen Fragen die Tutoren des Mathe-Helpdesks. Der Aufruf des Mathe-Chats ist erst nach dem Login möglich.
      </span>
                           ';

        
            $content .= '
                                <img class="message-icon" src="'.$CFG->wwwroot.'/theme/orca/pix/message.png">
                               <a id="mathe-chat-link" href="' . $CFG->wwwroot . '/local/mathe_physik_chat/index.php">Mathe-Chat</a>
                               <img class="mail-icon" src="'.$CFG->wwwroot.'/theme/orca/pix/mail.png">
                              <a class="help-links" href="mailto:mathe-helpdesk@orca.nrw">mathe-helpdesk@orca.nrw</a>
                               <img class="phone-icon" src="'.$CFG->wwwroot.'/theme/orca/pix/phone.png">
                               <a class="help-links" href="tel:+493066407267">030 6640 7267</a>
                            <p class="erreichbarkeit">Sie erreichen die Tutoren täglich (auch am Wochenende) von 10 bis 20 Uhr.</p>';
        
    } 
    if ($matheBereich) {
        $content .= '
                </div>
            </div>
        </div>
    </div>
</section>';
    }

    return $content;
}


/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_orca_get_main_scss_content($theme)
{
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/orca/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/orca/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_orca', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/orca/scss/preset/default.scss');
    }

    return $scss;
}
