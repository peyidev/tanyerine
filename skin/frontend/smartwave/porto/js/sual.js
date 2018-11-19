jQuery(document).ready(function(){
    jQuery(".youtube-popup").YouTubePopUp();

    jQuery(".subcategory-brands .index a").click(function (e){
    	e.preventDefault();
    	var id = jQuery(this).attr('href');
    	console.log(id);
        jQuery('html, body').animate({
            scrollTop: jQuery(id).offset().top - 100
        }, 500);
    });
});