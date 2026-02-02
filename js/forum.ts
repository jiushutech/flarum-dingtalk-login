import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LogInModal from 'flarum/forum/components/LogInModal';
import SignUpModal from 'flarum/forum/components/SignUpModal';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import IndexPage from 'flarum/forum/components/IndexPage';
import ItemList from 'flarum/common/utils/ItemList';
import DingtalkLoginButton from './src/forum/components/DingtalkLoginButton';
import DingtalkBindButton from './src/forum/components/DingtalkBindButton';
import DingtalkH5Auth from './src/forum/components/DingtalkH5Auth';
import DingtalkBindRequiredModal from './src/forum/components/DingtalkBindRequiredModal';
import DingtalkIndexCard from './src/forum/components/DingtalkIndexCard';

// 检查用户是否需要绑定钉钉
async function checkDingtalkBindingRequired() {
  // 未登录用户不检查
  if (!app.session.user) {
    return;
  }

  // 检查是否启用强制绑定
  if (!app.forum.attribute('dingtalkForceBind')) {
    return;
  }

  // 检查是否启用钉钉登录
  if (!app.forum.attribute('dingtalkLoginEnabled')) {
    return;
  }

  try {
    // 检查用户是否已绑定钉钉
    const response = await app.request({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/dingtalk/bind-status',
    });

    if (!response.bound) {
      // 显示强制绑定弹窗
      app.modal.show(DingtalkBindRequiredModal);
    }
  } catch (error: any) {
    // 如果API返回403，说明需要绑定
    if (error.status === 403) {
      app.modal.show(DingtalkBindRequiredModal);
    }
  }
}

app.initializers.add('jiushutech-dingtalk-login', () => {
  // 检测是否在钉钉环境中
  const isDingtalkEnv = /DingTalk/i.test(navigator.userAgent);
  
  // 如果在钉钉环境中且启用了H5登录，自动触发H5登录
  if (isDingtalkEnv && app.forum.attribute('dingtalkH5Enabled') && !app.session.user) {
    DingtalkH5Auth.autoLogin();
  }

  // 页面加载完成后检查是否需要强制绑定
  setTimeout(() => {
    checkDingtalkBindingRequired();
  }, 500);

  // 在登录弹窗中添加钉钉登录按钮
  extend(LogInModal.prototype, 'fields', function (items: ItemList<any>) {
    if (!app.forum.attribute('dingtalkLoginEnabled')) {
      return;
    }

    // 如果仅允许钉钉登录，隐藏其他登录方式
    if (app.forum.attribute('dingtalkOnlyLogin') === true) {
      items.remove('identification');
      items.remove('password');
      items.remove('remember');
      items.remove('submit');
    }

    items.add(
      'dingtalk-login',
      m(DingtalkLoginButton),
      -10
    );
  });

  // 在注册弹窗中添加钉钉登录按钮
  extend(SignUpModal.prototype, 'fields', function (items: ItemList<any>) {
    if (!app.forum.attribute('dingtalkLoginEnabled')) {
      return;
    }

    // 如果仅允许钉钉登录，隐藏其他注册方式
    if (app.forum.attribute('dingtalkOnlyLogin') === true) {
      items.remove('username');
      items.remove('email');
      items.remove('password');
      items.remove('submit');
    }

    items.add(
      'dingtalk-login',
      m(DingtalkLoginButton),
      -10
    );
  });

  // 在用户设置页面添加钉钉绑定选项（放在通知中心上面，纵向排列）
  extend(SettingsPage.prototype, 'settingsItems', function (items: ItemList<any>) {
    if (!app.forum.attribute('dingtalkLoginEnabled')) {
      return;
    }

    // 创建钉钉账号绑定区块
    const dingtalkSection = m('div.Settings-section.DingtalkSettings-section', [
      m('h3.Settings-heading', app.translator.trans('jiushutech-dingtalk-login.forum.settings.title')),
      m(DingtalkBindButton)
    ]);

    // 优先级设置为 80，使其在通知中心（优先级 70）上面
    items.add(
      'dingtalk-bind',
      dingtalkSection,
      80
    );
  });

  // 在论坛主页侧边栏显示钉钉信息卡片（可通过后台开关控制）
  extend(IndexPage.prototype, 'sidebarItems', function (items: ItemList<any>) {
    if (!app.forum.attribute('dingtalkLoginEnabled')) {
      return;
    }

    // 检查后台是否启用主页显示
    if (!app.forum.attribute('dingtalkShowOnIndex')) {
      return;
    }

    // 只有已登录用户才显示
    if (!app.session.user) {
      return;
    }

    items.add(
      'dingtalk-card',
      m(DingtalkIndexCard, { user: app.session.user }),
      -10
    );
  });
});
