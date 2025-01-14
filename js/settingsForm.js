(function(Drupal, drupalSettings) {
  Drupal.behaviors.ikCybersourceSettingsForm = {
    attach: async function(context) {
      if (context.querySelector('.form-element--type-text--uppercase')) {
        context.querySelectorAll('.form-element--type-text--uppercase').forEach(function(element) {
          Drupal.behaviors.ikCybersourceSettingsForm.textToUppercase(element)
          element.removeEventListener('keyup', Drupal.behaviors.ikCybersourceSettingsForm.textToUppercaseEvent)
          element.addEventListener('keyup', Drupal.behaviors.ikCybersourceSettingsForm.textToUppercaseEvent)
        })
      }
    },
    textToUppercaseEvent: function (event) {
      Drupal.behaviors.ikCybersourceSettingsForm.textToUppercase(event.target)
    },
    textToUppercase: function (input) {
      let value = input.value
      input.value = value.toUpperCase()
    }
  }
})(Drupal, drupalSettings)
