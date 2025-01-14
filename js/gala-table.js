(function(Drupal, drupalSettings) {
  Drupal.behaviors.ikCybersourceGalaTable = {
    attach: function(context) {
      if (context.querySelector('#edit-gala')) {
        Drupal.behaviors.ikCybersourceGalaTable.form = document.querySelector('form.webform-submission-form')
        Drupal.behaviors.ikCybersourceGalaTable.amount = Drupal.behaviors.ikCybersourceGalaTable.form.querySelector('input[name="amount"]')
        Drupal.behaviors.ikCybersourceGalaTable.amount.value = '0'

        Drupal.behaviors.ikCybersourceGalaTable.form.querySelectorAll('input[type="number"][data-drupal-selector$=quantity]').forEach(function(c) {
          c.addEventListener('change', Drupal.behaviors.ikCybersourceGalaTable.onQuanityChange)
        })

        Drupal.behaviors.ikCybersourceGalaTable.onQuanityChange()

        // Update the submit button event listener.
        const button = Drupal.behaviors.ikCybersourceGalaTable.form.querySelector('input[type="submit"]')
        button.addEventListener('click', Drupal.behaviors.ikCybersourceGalaTable.onSubmit)
      }
    },
    onQuanityChange: function(event) {
      let total = 0

      Drupal.behaviors.ikCybersourceGalaTable.form.querySelectorAll('input[type="number"][data-drupal-selector$=quantity]').forEach(function(c) {
        let value = c.value

        if (value.length === 0) {
          value = 0
        }

        total = total + (parseInt(c.getAttribute('data-amount') * value))
      })

      Drupal.behaviors.ikCybersourceGalaTable.amount.value = total
    },
    onSubmit: function (event) {
      Drupal.behaviors.ikCybersourceGalaTable.amount.removeAttribute('disabled')
    }
  }
})(Drupal, drupalSettings)
