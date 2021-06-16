jQuery(document).ready(function(){
    jQuery('.dtbaker-datepicker').datepicker({ dateFormat: 'yy-mm-dd' });
    jQuery('#square_create_new_invoice').click(function(e){
        e.preventDefault();
        jQuery('#square_create_invoice_form').show();
        return false;
    });
});
