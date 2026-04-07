(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.clubhouseBookingCalendar = {
    attach: function (context, settings) {
      $(context).find('#calendar').once('clubhouseBookingCalendar').each(function () {
        var calendarEl = this;
        var locale = drupalSettings.clubhouseBooking ? drupalSettings.clubhouseBooking.language : 'nl';
        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          locale: locale,
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
          },
          events: {
            url: '/api/clubhouse/slots',
            failure: function () {
              alert(Drupal.t('Failed to load calendar events.'));
            }
          },
          eventClick: function (info) {
            var status = info.event.extendedProps.status;
            if (status === 'free') {
              window.location.href = '/clubhouse/book/' + info.event.id;
            } else {
              var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
              alert(Drupal.t('This slot is already @status.', {'@status': Drupal.t(statusLabel)}));
            }
          }
        });
        calendar.render();

        // Add Request Custom Slot button below the calendar
        var $requestButton = $('<div class="calendar-actions"><a href="/clubhouse/request-custom-slot" class="button">' + Drupal.t('Request Custom Slot') + '</a></div>');
        $(calendarEl).before($requestButton);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
