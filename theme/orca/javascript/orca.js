// jQuery Anweisungen zur Gestaltung und Behebung von Moodle Fehldarstellungen
// Erstellt von: CG, 11/2016
// Vielen Dank an: Jungbaum Digitale Medien

$(document).ready(function(){
	$('.hamburger').click(function(e){
		e.preventDefault();
		$(this).toggleClass('no-hamburger');
        $('body').toggleClass('hamburger-active');
        $('#content').fadeToggle(0);
        $('footer').fadeToggle(0);
        $('header .keyvisual-bg').fadeToggle(0);
        $('header .header-text').fadeToggle(0);
        $(".owl-carousel-slider-angebot").removeClass('owl-hidden');
	});

    $('.hamburger').click(function(){
        $('nav .collapse').fadeToggle();
    });





    $('.mobile .dropdown-toggle-one > a').click(function(){
        $('.mobile .dropdown-toggle-one > ul').slideToggle();
        $('.mobile .dropdown-toggle-one').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-one .sub-menu .nav-mathe > a').click(function(){
        $('.mobile .dropdown-toggle-one .sub-menu .nav-mathe > ul').slideToggle();
        $('.mobile .dropdown-toggle-one .sub-menu .nav-mathe ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-one .sub-menu .nav-sput > a').click(function(){
        $('.mobile .dropdown-toggle-one .sub-menu .nav-sput > ul').slideToggle();
        $('.mobile .dropdown-toggle-one .sub-menu .nav-sput ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-one .sub-menu .nav-physik > a').click(function(){
        $('.mobile .dropdown-toggle-one .sub-menu .nav-physik > ul').slideToggle();
        $('.mobile .dropdown-toggle-one .sub-menu .nav-physik ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-one .sub-menu .nav-moveo > a').click(function(){
        $('.mobile .dropdown-toggle-one .sub-menu .nav-moveo > ul').slideToggle();
        $('.mobile .dropdown-toggle-one .sub-menu .nav-moveo ').toggleClass('rotate-arrow');
    });

    $('.mobile .dropdown-toggle-two > a').click(function(){
        $('.mobile .dropdown-toggle-two > ul').slideToggle();
        $('.mobile .dropdown-toggle-two').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-two .sub-menu .nav-mathe > a').click(function(){
        $('.mobile .dropdown-toggle-two .sub-menu .nav-mathe > ul').slideToggle();
        $('.mobile .dropdown-toggle-two .sub-menu .nav-mathe ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-two .sub-menu .nav-sput > a').click(function(){
        $('.mobile .dropdown-toggle-two .sub-menu .nav-sput > ul').slideToggle();
        $('.mobile .dropdown-toggle-two .sub-menu .nav-sput ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-two .sub-menu .nav-physik > a').click(function(){
        $('.mobile .dropdown-toggle-two .sub-menu .nav-physik > ul').slideToggle();
        $('.mobile .dropdown-toggle-two .sub-menu .nav-physik ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-two .sub-menu .nav-lernstrategien > a').click(function(){
        $('.mobile .dropdown-toggle-two .sub-menu .nav-lernstrategien > ul').slideToggle();
        $('.mobile .dropdown-toggle-two .sub-menu .nav-lernstrategien ').toggleClass('rotate-arrow');
    });
    $('.mobile .dropdown-toggle-two .sub-menu .nav-medienkompetenz > a').click(function(){
        $('.mobile .dropdown-toggle-two .sub-menu .nav-medienkompetenz > ul').slideToggle();
        $('.mobile .dropdown-toggle-two .sub-menu .nav-medienkompetenz ').toggleClass('rotate-arrow');
    });



    $('.mobile .dropdown-toggle-three > a').click(function(){
        $('.mobile .dropdown-toggle-three > ul').slideToggle();
        $('.mobile .dropdown-toggle-three').toggleClass('rotate-arrow');
    });
    $(".am-mediaplayer .btn-share").click(function(){
        if ( $(".am-mediaplayer .mejs-video").hasClass("mejs-container-fullscreen") ) {
            $(".am-mediaplayer .easy-popup-overlay").addClass("share-fullscreen");
        } else {
            $(".am-mediaplayer .easy-popup-overlay").removeClass("share-fullscreen");
        }
    });
    $(".btn-share").click(function(){
      $(".easy-popup-overlay").addClass("visible");
    });

    $(".easy-popup-close").click(function(){
      $(".easy-popup-overlay").removeClass("visible");
    });

    $(document).click(function(event) {
      //if you click on anything except the modal itself or the "open modal" link, close the modal
      if (!$(event.target).closest(".easy-popup,.btn-share").length) {
        $("body").find(".easy-popup-overlay").removeClass("visible");
      }
    });

    $('.share-fb').attr('href', $('.mejs-share-fb').attr('href'));
    $('.share-tw').attr('href', $('.mejs-share-tw').attr('href'));
});
$(document).ready(function() {

//Funktion zur automatischen Skalierung der MathJax-Formelgrößen
console.log(" ### Responsive formula resizer is active ### ");
  
  vemint_mq = window.matchMedia('(min-width: 992px)');
  mobile_ansicht = !vemint_mq.matches;
  /* direkt in MathJax-Konfiguration eingebunden
  if (mobile_ansicht) {
        MathJax.Hub.Config({
                 "HTML-CSS": { scale: 80}
        });
        MathJax.Hub.Queue(["Reprocess", MathJax.Hub]);
        console.log("GO SMALL at START");
  }
*/
  $( window ).resize(function() {
    //console.log("" + vemint_mq.matches + " && " + mobile_ansicht);
    if (vemint_mq.matches && mobile_ansicht) {
        mobile_ansicht = false;
        MathJax.Hub.Config({
                 "HTML-CSS": { scale: 100}
        });
        MathJax.Hub.Queue(["Reprocess", MathJax.Hub]);
        console.log("BIG SIZE");
    } else if (!vemint_mq.matches && !mobile_ansicht) {
        mobile_ansicht = true;
        MathJax.Hub.Config({
                 "HTML-CSS": { scale: 80}
        });
        MathJax.Hub.Queue(["Reprocess", MathJax.Hub]);
        console.log("SMALL SIZE");
    }
  });


// Funktion zum Zählen von Wörtern
    $.fn.wrapStart = function (numWords) { 
    var node = this.contents().filter(function () { return this.nodeType == 3 }).first(),
        text = node.text(),
        first = text.split(" ", numWords).join(" ");

    if (!node.length)
        return;
    
    node[0].nodeValue = text.slice(first.length);
    node.before('<span class=\"le\">' + first + '</span>');
};
/*
// Mechanismus zum Weiterblättern nach einem Radio-Button Click bei freien Fragen - Start
if($(".que.multichoice.deferredfeedback .content .formulation.clearfix .ablock .answer.collapse > div")[0])
{	
	var children = $(".que.multichoice.deferredfeedback .content .formulation.clearfix .ablock .answer.collapse > div")[0].childNodes;
	var freieFrage = false;
	for(var i=0; i<children.length; i++)
	{
		if	(children[i].nodeType === 8 && children[i].nodeValue == "Freie Frage")
		{
			freieFrage = true;
		}
	}
	
	if (freieFrage)
	{
		$('input[type="submit"][value="Nächste Seite"]').css("opacity", "0.0");
		$('input[type="submit"][value="Zur Aufgabenübersicht"]').css("opacity", "0.0");
		$('input[type="submit"][value="Vorherige Seite"]').css("opacity", "0.0");

		$('input[type="radio"]').change(function()
			{
				$('input[type="submit"][value="Nächste Seite"]').click();
				$('input[type="submit"][value="Zur Aufgabenübersicht"]').click();
			}
		);
	}
}
// Mechanismus zum Weiterblättern nach einem Radio-Button Click bei freien Fragen - End
*/

// "LE 1" etc. im Quadrat auswählen - wird nur im Mathebereich im Theme eingebunden
// $(".quadrat h3").wrapStart(2);



// ################# Mathe Kursübersicht
if (window.location.href.indexOf('categoryid=3') > -1)
{

  // VEMINT@NRW anders platzieren
  $('#region-main h2:contains(VEMINT)').addClass('vemint');
  $('#region-main h2.vemint').unwrap(); // entfernt Parent DIV mit inline-CSS
  
  var vemint = $('#region-main h2.vemint').remove(); //H2 Tag mit Inhalt wird ausgeschnitten (in die Zwischenablage kopiert)
  vemint.appendTo('#region-main header h1'); // und dann in den Inhalt des H1-Tags hinter "Mathematik" eingefügt
  $('#region-main h2.vemint').wrapInner('<span></span>'); //Um den Inhalt "StudiVEMINT" innherhalb des H2 Tags wird ein span gelegt
  $('#region-main h2.vemint span').before(': ').addClass('vemint'); //dieser span bekommt die Klasse "vemint" zugewiesen
  $('#region-main h2.vemint').contents().unwrap(); // entfernt das H2 Tag

  // In der Kursübersicht "LE 1" etc. mit einem span-Tag umschließen
  $('.category-browse-3 h3.categoryname a').each(function() {
  $( this ).wrapStart(2);
  });

	
	
      
   
 
} // End if


//Versuchsübersichtsseiten in Übungen überspringen
	if (!(document.body.className.match("category-33") || document.body.className.match("category-34")))		//Nicht bei Wissenstests
	{
		if (location.pathname.endsWith("/mod/quiz/view.php")) {
							tmplist = document.getElementsByTagName("input");
							tmplist[0].click();
						}


		if (window.location.href.indexOf('/mod/quiz/review.php') > -1)
		{
			$('#bewertungsbutton').click();	
		}
	}


//Button "Test jetzt durchführen" bei Übungen auf "Übung jetzt durchführen" ändern
if ($('#page-mod-quiz-view').length)
{
	if(!($(".course-147").length || $(".course-149").length)) {
		$('input[type="submit"][value="Test jetzt durchführen"]').val('Übung jetzt durchführen');
	}
}


// Buttons bei Wissenstests von "Übung" auf "Test" ändern
if($(".course-147").length || $(".course-149").length) {
	$('input[type="submit"][value="Übung beenden"]').val('Test beenden');
	$('input[type="submit"][value="Übung neu starten"]').val('Test neu starten');
}


// ################# Übungen: Gestaltung und Fehldarstellungen beheben
if (window.location.href.indexOf('review.php') > -1)
{
    // Altes Info Zeichen entfernen, weil es an unzähligen Stellen mit unlogischem inline-CSS eingebunden wurde
    $('.content .formulation img[src*="info"]').remove();
}

if (window.location.href.indexOf('attempt.php') > -1)
{

// Fragen selektieren mit p:contains(?) und fett drucken
/*$('.content .formulation p:contains(?)').each(function() {
$(this).wrapInner('<strong></strong>');
});*/

// Altes Info Zeichen entfernen, weil es an unzähligen Stellen mit unlogischem inline-CSS eingebunden wurde
$('.content .formulation img[src*="info"]').remove();

// inline-CSS aus dem Infotext komplett entfernen
$('.informationitem .content .formulation p').each(function() {
  $(this).attr("style", "");
});

// Icons für Richtig und Falsch ersetzen durch FontAwesome Schrift
$('.content .answer img[src*="grade_correct"]').replaceWith('<span class=\"fa fa-check questioncorrectnesicon\"><i class=\"sr-only\">Richtig!</i></span>');
$('.content .answer img[src*="grade_incorrect"]').replaceWith('<span class=\"fa fa-times questioncorrectnesicon\"><i class=\"sr-only\">Falsch!</i></span>');

// Textauszüge sollen nicht mehr in font-size:small erscheinen
/*$('.content .formulation *:not(.btn), .content .outcome *:not(.btn)').css('font-size','inherit');*/

// Aufgaben Navigation umbenennen, da schlicht "Navigation" hier verwirrt
$('#mod_quiz_navblock h2 #mod_quiz_navblock_title').text('Aufgaben Schnellzugriff');

} // End if

/*
// ################# Smooth Scrolling für "nach oben"; ist aber auch für weitere Elemente möglich
$('.jumpup').on('click', function(event) {

// Make sure this.hash has a value before overriding default behavior
if (this.hash !== "") {

// Prevent default anchor click behavior
event.preventDefault();

// Store hash
var hash = this.hash;

// Using jQuery's animate() method to add smooth page scroll
// The optional number (900) specifies the number of milliseconds it takes to scroll to the specified area
$('html, body').animate({
      scrollTop: $(hash).offset().top
    }, 900, function(){

// Add hash (#) to URL when done scrolling (default click behavior)
      window.location.hash = hash;
      });
} // End if 
});*/
      var offset = 750;
    var duration = 300;
    jQuery(window).scroll(function() {
        if (jQuery(this).scrollTop() > offset) {
            jQuery('.jumpup').fadeIn(duration);
        } else {
            jQuery('.jumpup').fadeOut(duration);
        }
    });

    jQuery('.jumpup').click(function(event) {
        event.preventDefault();
        jQuery('html, body').animate({scrollTop: $('#page').position().top}, duration);
        return false;
    }) 

// Moodle Action Menu: Dashboard Link wird erstmal nicht angezeigt, später einfach auskommentieren
$('#action-menu-0-menu li:contains(Dashboard)').remove();
$('#action-menu-0-menu li:contains(Profil)').prev().remove();
// Auf der Profilseite den Button "Dashboard bearbeiten" entfernen
$('div.breadcrumb-button input[value*="Dashboard"]').remove();

// Loginformular unten: Fragezeichensymbol ersetzen durch FontAwesome
$('.loginpanel .helptooltip img[src*="help"]').replaceWith(' <span class=\"fa fa-question-circle\"><i class=\"sr-only\">Hilfe zu Cookies</i></span>');




// jQuery Responsive via matchMedia (state of the art); Polyfill für IE9 erforderlich
var mq = window.matchMedia('(min-width: 992px)');
var mq_ereignis = function(mq) {
  if (mq.matches){ 
    // MD und LG

 //   $('#region-main').css('font-size', '20px');	
	
    $('.block_leblock .content').css('display', 'block');

    $('h2.klappe').remove();
		

  } else {
    // XS und SM
//	$('#region-main').css('font-size', '8px');	
	
    $('.block_leblock .content').before('<h2 class=\"klappe\">Kursnavigation</h2>'); 

    $('.block_leblock .content').css('display', 'none');
    
    $('h2.klappe').click(function(){
    
    $('.block_leblock .content').slideToggle('slow');
    $('h2.klappe').toggleClass('expanded');
	

    });
    //Ende
}
};
    
    //------------------Bildschirmlupe---------------------
    
    var native_width = 0;
	var native_height = 0;

	//Mouseoverfunktion
	$(".magnify").mousemove(function(e){
		//Sobald der Nutzer über das Bild hovert, kalkuliert das Script zuerst
		//die ursprünglichen Maße sofern sie nicht existieren. Nur wenn diese sichtbar sind wird die Lupe sichtbar.
		if(!native_width && !native_height)
		{
			//Erstellt ein neues Image-Objekt mit dem selben Bild der .small Klasse da man nicht direkt die Maße des Ursprungsbildes erkennen kann
			var image_object = new Image();
			image_object.src = $(".small").attr("src");
			native_width = image_object.width;
			native_height = image_object.height;
		}
		else
		{
			//x/y der Maus
			var magnify_offset = $(this).offset();
			var mx = e.pageX - magnify_offset.left;
			var my = e.pageY - magnify_offset.top;
			
			if(mx < $(this).width() && my < $(this).height() && mx > 0 && my > 0)
			{
				$(".large").fadeIn(100);
			}
			else
			{
				$(".large").fadeOut(100);
			}
			if($(".large").is(":visible"))
			{
				//Die Position des Hintergrundbildes wird in Abhängigkeit der Position der Maus geändert
				var rx = Math.round(mx/$(".small").width()*native_width - $(".large").width()/2)*-1;
				var ry = Math.round(my/$(".small").height()*native_height - $(".large").height()/2)*-1;
				var bgp = rx + "px " + ry + "px";
				
				//Bewegt die Lupe mit der Maus
				var px = mx - $(".large").width()/2;
				var py = my - $(".large").height()/2;
				
				$(".large").css({left: px, top: py, backgroundPosition: bgp});
			}
		}
	})
    
    //------------------Rhetorikbaum-----------------------


    //----- OPEN
    $('[data-popup-open]').on('click', function(e)  {
        var targeted_popup_class = jQuery(this).attr('data-popup-open');
        $('[data-popup="' + targeted_popup_class + '"]').fadeIn(350);
        
 
        e.preventDefault();
        $('body').addClass("body-no-scroll");
    });
    
 
    //----- CLOSE
    $('[data-popup-close]').on('click', function(e)  {
        var targeted_popup_class = jQuery(this).attr('data-popup-close');
        $('[data-popup="' + targeted_popup_class + '"]').fadeOut(350);
        
        
        e.preventDefault();
        $('body').removeClass("body-no-scroll");
        
    });
    $(document).on('click', function(event) {
        if ($(event.target).has('.popup-inner').length) {
            $(".popup").fadeOut(350);
            $('body').removeClass("body-no-scroll");
        }
    });
        
        
    $('.tropen-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active tropen-hover")
        $('#tropen.grosser_ast').addClass("baum-active")
    });
    $('.tropen-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active tropen-hover")
        $('#tropen.grosser_ast').removeClass("baum-active")
    });
    
    $('.metapher-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active metapher-hover")
        $('.metapher').addClass("baum-active")
    });
    $('.metapher-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active metapher-hover")
        $('.metapher').removeClass("baum-active")
    });
    
    $('.umschreibungen-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active umschreibungen-hover")
        $('.umschreibungen').addClass("baum-active")
    });
    $('.umschreibungen-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active umschreibungen-hover")
        $('.umschreibungen').removeClass("baum-active")
    });
    
    $('.hinzufuegungsprinzip-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active hinzufuegungsprinzip-hover")
        $('.hinzufuegungsprinzip').addClass("baum-active")
    });
    $('.hinzufuegungsprinzip-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active hinzufuegungsprinzip-hover")
        $('.hinzufuegungsprinzip').removeClass("baum-active")
    });
    
    $('.wortspiel-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active wortspiel-hover")
        $('.wortspiel').addClass("baum-active")
    });
    $('.wortspiel-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("active wortspiel-hover")
        $('.wortspiel').removeClass("baum-active")
    });
    
    $('.aufzaehlung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active aufzaehlung-hover")
        $('.baum-aufzaehlung').addClass("baum-active")
    });
    $('.aufzaehlung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active aufzaehlung-hover")
        $('.baum-aufzaehlung').removeClass("baum-active")
    });
    
    $('.kunstvolle-wiederholung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("active kunstvolle-wiederholung-hover")
        $('.kunstvolle-wiederholung').addClass("active")
    });
    $('.kunstvolle-wiederholung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active kunstvolle-wiederholung-hover")
        $('.kunstvolle-wiederholung').removeClass("baum-active")
    });
    
    $('.lautfiguren-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active lautfiguren-hover")
        $('.lautfiguren').addClass("baum-active")
    });
    $('.lautfiguren-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active lautfiguren-hover")
        $('.lautfiguren').removeClass("baum-active")
    });
    
    $('.auslassungsprinzip-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active auslassungsprinzip-hover")
        $('.auslassungsprinzip').addClass("baum-active")
    });
    $('.auslassungsprinzip-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active auslassungsprinzip-hover")
        $('.auslassungsprinzip').removeClass("baum-active")
    });
    
    $('.umstellungsprinzip-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active umstellungsprinzip-hover")
        $('.umstellungsprinzip').addClass("baum-active")
    });
    $('.umstellungsprinzip-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active umstellungsprinzip-hover")
        $('.umstellungsprinzip').removeClass("baum-active")
    });
    
    $('.hinzufuegungsprinzip-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active hinzufuegungsprinzip-hover")
        $('.hinzufuegungsprinzip').addClass("baum-active")
    });
    $('.hinzufuegungsprinzip-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active hinzufuegungsprinzip-hover")
        $('.hinzufuegungsprinzip').remoceClass("baum-active")
    });
    
    $('.aufzaehlung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active aufzaehlung-hover")
        $('.aufzaehlung').addClass("baum-active")
    });
    $('.aufzaehlung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active aufzaehlung-hover")
        $('.aufzahelung').addClass("baum-active")
    });
    
    $('.kunstvolle-wiederholung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active kunstvolle-wiederholung-hover")
        $('.kunstvolle-wiederholung').addClass("baum-active")
    });
    $('.kunstvolle-wiederholung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active kunstvolle-wiederholung-hover")
        $('.kunstvolle-wiederholung').removeClass("baum-active")
    });
    
    $('.lautfiguren-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active lautfiguren-hover")
        $('.lautfiguren').addClass("baum-active")
    });
    $('.lautfiguren-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active lautfiguren-hover")
        $('.lautfiguren').removeClass("baum-active")
    });
    
    $('.umstellungsprinzip-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active umstellungsprinzip-hover")
        $('.umstellungsprinzip').addClass("baum-active")
    });
    $('.umstellungsprinzip-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active umstellungsprinzip-hover")
        $('.umstellungsprinzip').removeClass("baum-active")
    });
    
    $('.wortfiguren-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active wortfiguren-hover")
        $('#wortfiguren.grosser_ast').addClass("baum-active")
    });
    $('.wortfiguren-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active wortfiguren-hover")
        $('#wortfiguren.grosser_ast').removeClass("baum-active")
    });
    
    
    $('.sinn-gedankenfiguren-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active sinn-gedankenfiguren-hover")
        $('#sinn-gedankenfiguren').addClass("baum-active")
    });
    $('.sinn-gedankenfiguren-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active sinn-gedankenfiguren-hover")
        $('#sinn-gedankenfiguren').removeClass("baum-active")
    });
    
    $('.sinnpraezisierung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active sinnpraezisierung-hover")
        $('.sinnpraezisierung').addClass("baum-active")
    });
    $('.sinnpraezisierung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active sinnpraezisierung-hover")
        $('.sinnpraezisierung').removeClass("baum-active")
    });
    
    $('.satzordnung-hover-box').on('mouseenter', function(){
        $('.hover-overlay').addClass("baum-active satzordnung-hover")
        $('.satzordnung').addClass("baum-active")
    });
    $('.satzordnung-hover-box').on('mouseleave', function(){
        $('.hover-overlay').removeClass("baum-active satzordnung-hover")
        $('.satzordnung').removeClass("baum-active")
    });
    
     $(".index-nav").click(function(){
        $(".index").show(400);
        $(".rhetorikbaum nav ul li a").removeClass("active");
        $(this).addClass("active");
        $(".baumstruktur").hide();
        $(".tabstruktur").hide();
    });
    $(".baumstruktur-nav").click(function(){
        $(".baumstruktur").show(400);
        $(".rhetorikbaum nav ul li a").removeClass("active");
        $(this).addClass("active");
        $(".index").hide();
        $(".tabstruktur").hide();
    });
    $(".tabstruktur-nav").click(function(){
        $(".tabstruktur").show(400);
        $(".rhetorikbaum nav ul li a").removeClass("active");
        $(this).addClass("active");
        $(".index").hide();
        $(".baumstruktur").hide();
    })
    /*---------------Sidebar--------------*/
    
    $(".tutor-infoblock").click(function() {
        $(".tutor-block").toggleClass("visibility"); 
        $(".sidescroll-button > i").toggleClass("visibility"); 
    });
        $(".umfrage-infoblock").click(function() {
        $(".umfrage-block").toggleClass("visibility"); 
        $(".sidescroll-button-umfrage > i").toggleClass("visibility"); 
    });
    
    /*---------Textfeld-Collapse---------*/
    
    $(".collapse-button").click(function() {
        $(".layoutbox").toggleClass("text-scroll"); 
        $(".collapse-button > i").toggleClass("full_text"); 
    });  
    
   
     

    
    /*----------------------------------*/
    
    
    
    
mq_ereignis(mq);
mq.addListener(mq_ereignis);

/*
    $(".vemint_video").fancybox({
        fitToView   : false,
        autoSize    : false,
        closeClick  : true,
        closeBtn    : true,
        openEffect  : 'none',
        closeEffect : 'none',
        scrolling   : 'no',
        href        : this.href,
        
        onCleanup  : function (){
            $('#vemint_video')[0].pause();
        }

    });    */

});

/*--------------Navigation--------------*/

jQuery(document).ready(function($){
    
//Funktion um die Abspielrate der Videos zu ändern
	var elements = document.getElementsByClassName("fast-forward");
	if ( elements.length > 0 ) {
		for (  var i = 0; i < elements.length; i++) {
			var ele = elements[i];
		//for ( ele of elements ) {
			ele.parentElement.onclick = function (event) {
				button=event.target.parentElement;
				var video=button.parentElement.parentElement.parentElement.getElementsByTagName("video")[0];
				if (button.classList.contains("active")) {
					button.classList.remove("active");
					video.playbackRate = 1;
				} else {
					button.classList.add("active");
					video.playbackRate = 1.25;
				}
			};
		 }
	}
});


		// Testsequenz für die neue Navigation
    $('.collapse.in').prev('.panel-heading').addClass('active');
    $('#accordion, #bs-collapse')
		.on('show.bs.collapse', function(a) {
		  $(a.target).prev('.panel-heading').addClass('active');
             
		})
		.on('hide.bs.collapse', function(a) {
		  $(a.target).prev('.panel-heading').removeClass('active');
		});
		// Testsequenz - ENDE

//Hinweis-Banner bis zum Browserende abschalten
function close_notice(e) {
    e.parentElement.style.display = "none";
    document.cookie = "closenotice=";
}

//Rechtsklick-Sperre auf alle Bilder
$(document).ready(function(){
    $("img").bind("contextmenu",function(e){
        return false;
    });
});

//Fancybox für das Video auf der Mathematik-Unterseite
//Script für iOS/Safari Hover Workaround
document.addEventListener("touchstart", function(){}, true);
