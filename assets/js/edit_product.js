jQuery(function($) {
  'use strict';

  const lmfwcBindWcsEvents = function() {
    let onSubscriptionRenewal = document.querySelectorAll('select.lmfwc_subscription_renewal_action');
    let resetAction = document.querySelectorAll('select.lmfwc_subscription_renewal_reset_action');
    let subscriptionModel = document.querySelectorAll('select.lmfwc_subscription_model_type');
    let maxIncludedActivations = document.querySelectorAll('input.lmfwc_maximum_included_activations');
    let extendBy = document.querySelectorAll('select.lmfwc_subscription_renewal_interval_type');
    let interval = document.querySelectorAll('input.lmfwc_subscription_renewal_custom_interval');
    let period = document.querySelectorAll('select.lmfwc_subscription_renewal_custom_period');

    if (!onSubscriptionRenewal || !resetAction || !subscriptionModel || !maxIncludedActivations || !extendBy || !interval || !period) {
      return;
    }

    for (let i = 0; i < onSubscriptionRenewal.length; i++) {
      onSubscriptionRenewal[i].addEventListener('change', function(event) {
        const value = event.target.value;

        if (value === 'issue_new_license') {
          resetAction[i].parentNode.style.display = 'none';
          maxIncludedActivations[i].parentNode.style.display = 'none';
          subscriptionModel[i].parentNode.style.display = 'none';
          extendBy[i].parentNode.style.display = 'none';
          interval[i].parentNode.style.display = 'none';
          period[i].parentNode.style.display = 'none';
        } else {
          resetAction[i].parentNode.style.display = 'block';
          subscriptionModel[i].parentNode.style.display = 'block';
          maxIncludedActivations[i].parentNode.style.display = 'block';
          extendBy[i].parentNode.style.display = 'block';

          if (resetAction[i].value === 'do_not_reset_on_renewal') {
            subscriptionModel[i].parentNode.style.display = 'none';
            maxIncludedActivations[i].parentNode.style.display = 'none';
          } else {
            subscriptionModel[i].parentNode.style.display = 'block';
            maxIncludedActivations[i].parentNode.style.display = 'block';

            if (subscriptionModel[i].value === 'fixed_usage_type') {
              maxIncludedActivations[i].parentNode.style.display = 'none';
            } else {
              maxIncludedActivations[i].parentNode.style.display = 'block';
            }
          }

          if (extendBy[i].value === 'subscription') {
            interval[i].parentNode.style.display = 'none';
            period[i].parentNode.style.display = 'none';
          } else {
            interval[i].parentNode.style.display = 'block';
            period[i].parentNode.style.display = 'block';
          }
        }
      });
    }

    for (let j = 0; j < resetAction.length; j++) {
      resetAction[j].addEventListener('change', function(event) {
        const value = event.target.value;

        if (value === 'do_not_reset_on_renewal') {
          subscriptionModel[j].parentNode.style.display = 'none';
          maxIncludedActivations[j].parentNode.style.display = 'none';
        } else {
          subscriptionModel[j].parentNode.style.display = 'block';
          maxIncludedActivations[j].parentNode.style.display = 'block';

          if (subscriptionModel[j].value === 'fixed_usage_type') {
            maxIncludedActivations[j].parentNode.style.display = 'none';
          } else {
            maxIncludedActivations[j].parentNode.style.display = 'block';
          }
        }
      });
    }

    for (let j = 0; j < subscriptionModel.length; j++) {
      subscriptionModel[j].addEventListener('change', function(event) {
        const value = event.target.value;

        if (value === 'fixed_usage_type') {
          maxIncludedActivations[j].parentNode.style.display = 'none';
        } else {
          maxIncludedActivations[j].parentNode.style.display = 'block';
        }
      });
    }

    for (let j = 0; j < extendBy.length; j++) {
      extendBy[j].addEventListener('change', function(event) {
        const value = event.target.value;

        if (value === 'subscription') {
          interval[j].parentNode.style.display = 'none';
          period[j].parentNode.style.display = 'none';
        } else {
          interval[j].parentNode.style.display = 'block';
          period[j].parentNode.style.display = 'block';
        }
      });
    }
  }

  lmfwcBindWcsEvents();

  $('#woocommerce-product-data').on('woocommerce_variations_loaded', lmfwcBindWcsEvents);
  $('#variable_product_options').on('reload', lmfwcBindWcsEvents);
});