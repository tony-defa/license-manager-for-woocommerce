document.addEventListener('DOMContentLoaded', function(event) {
    
    const id = 'lmfwc_subscription_renewal_action';

    updateFields();

    jQuery('.woocommerce_variations.wc-metaboxes').one('click', function() {
        updateFields();
    });

    function updateFields() {
        jQuery(`[id*=${id}]`).each(function(index, el) {
            index = el.id.replace(id, '');

            jQuery(`#${id}${index}`).on('change', function(e) {
                var disable = jQuery(this).val() === 'issue_new_license';
                jQuery(`#lmfwc_subscription_renewal_reset_action${index}`).prop('disabled', disable);
                jQuery(`#lmfwc_subscription_renewal_interval_type${index}`).prop('disabled', disable);
                jQuery(`#lmfwc_subscription_renewal_custom_interval${index}`).prop('disabled', disable);
                jQuery(`#lmfwc_subscription_renewal_custom_period${index}`).prop('disabled', disable);
            });
        })
    }
});