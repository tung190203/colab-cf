self.addEventListener('push', function(event) {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(
      self.registration.showNotification(data.title || 'Booking mới', {
        body: data.body || 'Bạn có booking mới!',
        icon: data.icon || '/icon-192x192.png',
        data: data.url || '/new-bookings',
      })
    );
  });
  
  self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(
      clients.openWindow(event.notification.data)
    );
  });
  