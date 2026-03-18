(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.clubhouseBookingCalendar = {
    attach: function (context, settings) {
      $(context).find('#calendar').once('clubhouseBookingCalendar').each(function () {
        var calendarInstance = new calendarJs(this, {
          exportEventsEnabled: false,
          manualEditingEnabled: false,
          showRefreshButton: true,
          events: []
        });

        // Fetch events from our API.
        $.getJSON('/api/clubhouse/slots', function (data) {
          var events = data.map(function (item) {
            return {
              id: item.id,
              title: item.title,
              from: new Date(item.from),
              to: new Date(item.to),
              color: item.color,
              description: item.isBooked ? Drupal.t('Already booked') : Drupal.t('Click to book this slot'),
              isBooked: item.isBooked
            };
          });
          calendarInstance.addEvents(events);
        });

        calendarInstance.setOptions({
          onEventClick: function (event) {
            if (!event.isBooked) {
              window.location.href = '/clubhouse/book/' + event.id;
            } else {
              alert(Drupal.t('This slot is already booked.'));
            }
          }
        });

        // Add Request Custom Slot button below the calendar
        var $requestButton = $('<div class="calendar-actions" style="margin-top: 20px;"><a href="/clubhouse/request-custom-slot" class="button">' + Drupal.t('Request Custom Slot') + '</a></div>');
        $(this).after($requestButton);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
