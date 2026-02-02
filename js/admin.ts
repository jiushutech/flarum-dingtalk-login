import app from 'flarum/admin/app';
import DingtalkSettingsPage from './src/admin/components/DingtalkSettingsPage';

app.initializers.add('jiushutech-dingtalk-login', () => {
  app.extensionData
    .for('jiushutech-dingtalk-login')
    .registerPage(DingtalkSettingsPage);
});
