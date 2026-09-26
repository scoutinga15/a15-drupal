(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.clubhouseBookingCalendar = {
    attach: function (context, settings) {
      $(once('clubhouseBookingCalendar', '#calendar', context)).each(function () {
        var calendarEl = this;
        var locale = drupalSettings.clubhouseBooking ? drupalSettings.clubhouseBooking.language : 'nl';
        var calendar;
        calendar = new FullCalendar.Calendar(calendarEl, {
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
          eventSources: [
            {
              url: '/api/clubhouse/slots',
              failure: function () {
                alert(Drupal.t('Failed to load calendar events.'));
              }
            },
            {
              url: '/api/clubhouse/holidays',
              method: 'GET',
              extraParams: function() {
                var year = new Date().getFullYear();
                if (calendar) {
                  year = calendar.getDate().getFullYear();
                }
                return {
                  year: year
                };
              },
              color: '#f0f0f0',
              textColor: '#999',
              display: 'background'
            }
          ],
          eventClick: function (info) {
            // Do nothing for holidays.
            if (info.event.display === 'background') {
              return;
            }
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
        var $requestButton = $('<div class="calendar-actions"><a href="/clubhouse/request-custom-slot" class="button">' + Drupal.t('Request a date without obligation') + '</a></div>');
        $(calendarEl).before($requestButton);
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
