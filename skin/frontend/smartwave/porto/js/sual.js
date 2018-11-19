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
    jQuery('body').on('click','.layer-filter-icon, .close-mobile-layer, .close-layer',function(event) { 
        if(!jQuery('body').hasClass('mobile-layer-shown')) {
            jQuery('body').addClass('mobile-layer-shown', function() { 
                setTimeout(function(){
                    jQuery(document).one("click",function(e) {
                        var target = e.target;
                        if (!jQuery(target).is('.block-main-layer .block') && !jQuery(target).parents().is('.block-main-layer .block')) {
                                    jQuery('body').removeClass('mobile-layer-shown');
                        }
                    });  
                }, 111);
            });
        } else{
            jQuery('body').removeClass('mobile-layer-shown');
        }
    }); 
});