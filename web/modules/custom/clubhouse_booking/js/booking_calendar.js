(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.clubhouseBookingCalendar = {
    attach: function (context, settings) {
      $(context).find('#calendar').once('clubhouseBookingCalendar').each(function () {
        var calendarEl = this;
        var locale = drupalSettings.clubhouseBooking ? drupalSettings.clubhouseBooking.language : 'nl';
        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'multiMonthYear',
          locale: locale,
          selectable: true,
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
          },
          select: function(info) {
            // FullCalendar end date is exclusive, so for single day selection it's the next day.
            // We want to pass the last inclusive day to the form.
            var start = info.startStr;
            var endDate = new Date(info.end);
            endDate.setDate(endDate.getDate());
            var end = endDate.toISOString().split('T')[0];

            window.location.href = '/clubhouse/request-custom-slot?start=' + start + '&end=' + end;
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
              var statusLabel = status;
              if (status === 'requested') statusLabel = Drupal.t('Requested');
              if (status === 'reserved') statusLabel = Drupal.t('Reserved');
              if (status === 'booked') statusLabel = Drupal.t('Booked');
              alert(Drupal.t('This date is already @status.', {'@status': statusLabel}));
            }
          }
        });
        calendar.render();

        // Add Request Custom Slot button below the calendar
        var $requestButton = $('<div class="calendar-actions"><a href="/clubhouse/request-custom-slot" class="button">' + Drupal.t('Request another date') + '</a></div>');
        $(calendarEl).before($requestButton);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
