function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);

    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

export async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    return navigator.serviceWorker.register('/sw.js');
}

export async function enablePushNotifications(token = null) {
    const cfg = window.APP_CONFIG || {};

    if (!cfg.vapidPublicKey || !('PushManager' in window) || !('Notification' in window)) {
        return;
    }

    if (Notification.permission === 'denied') {
        return;
    }

    const registration = await registerServiceWorker();
    if (!registration) {
        return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return;
    }

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(cfg.vapidPublicKey),
        });
    }

    await fetch('/push/subscribe', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': cfg.csrfToken,
        },
        body: JSON.stringify({ ...subscription.toJSON(), token }),
    });
}
