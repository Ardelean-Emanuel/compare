(function($) {
    $(document).ready(function() {
        
        const asideArea = $('#page-mod-book-view section[data-region="blocks-column"]');
        const hasBlocksArea = $('.page-content').find('#page-mod-book-view section[data-region="blocks-column"]').length > 0;
        console.log('HasBlockArea inside page-content:'+hasBlocksArea);
        if(!hasBlocksArea) {
            $('.page-content').append(asideArea);
        }

    });
})(jQuery);