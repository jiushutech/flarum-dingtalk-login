import app from 'flarum/admin/app';
import DingtalkSettingsPage from './src/admin/components/DingtalkSettingsPage';
import extendUserListPage from './src/admin/extendUserListPage';

app.initializers.add('jiushutech-dingtalk-login', () => {
  app.extensionData
    .for('jiushutech-dingtalk-login')
    .registerPage(DingtalkSettingsPage);

  // 扩展用户列表页面
  extendUserListPage();
});
