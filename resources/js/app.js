import Alpine from 'alpinejs';
import clientChat from './client-chat';
import operatorApp from './operator-dashboard';

window.Alpine = Alpine;
Alpine.data('clientChat', clientChat);
Alpine.data('operatorApp', operatorApp);
Alpine.start();
