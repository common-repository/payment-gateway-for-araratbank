var $ = jQuery;
$(document).ready(function () {

    checkCheckboxes();

    $(document).on('change', '#woocommerce_hkd_araratbank_save_card', function () {
        checkCheckboxes();
    });

    $(document).on('mouseover', '.woocommerce-help-tip', function () {
        let parentId = $(this).parent().attr('for');
        if (parentId === 'woocommerce_hkd_araratbank_save_card_button_text') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScript.pluginsUrl + 'assets/images/bindingnew.jpg" width="300">');
        } else if(parentId === 'woocommerce_hkd_araratbank_save_card_header') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScript.pluginsUrl + 'assets/images/payment.jpg" width="300">');
        }else if(parentId === 'woocommerce_hkd_araratbank_save_card_use_new_card'){
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScript.pluginsUrl + 'assets/images/newcard.jpg" width="300">');
        }else{
            $('#tiptip_content').css({'max-width': '150px'});
        }
    });

    function checkCheckboxes() {
        $('.hiddenValue').parents('tr').hide();
        let saveCardMode = $('#woocommerce_hkd_araratbank_save_card').is(':checked');
        if (saveCardMode) {
            $('.saveCardInfo').parents('tr').show();
        } else {
            $('.saveCardInfo').parents('tr').hide();
        }
    }
});
