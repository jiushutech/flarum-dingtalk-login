import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class DingtalkLoginButton extends Component {
  loading: boolean = false;

  // 检测是否在钉钉环境中
  isDingtalkEnvironment(): boolean {
    if (typeof navigator === 'undefined') return false;
    return /DingTalk/i.test(navigator.userAgent);
  }

  view() {
    const isDingtalkEnv = this.isDingtalkEnvironment();

    return (
      <div className="DingtalkLoginButton">
        <div className="DingtalkLoginButton-divider">
          <span>{app.translator.trans('jiushutech-dingtalk-login.forum.login.or')}</span>
        </div>
        <Button
          className="Button Button--block DingtalkLoginButton-button"
          loading={this.loading}
          onclick={this.handleClick.bind(this)}
        >
          <span className="DingtalkLoginButton-icon">
            <svg viewBox="0 0 1024 1024" width="20" height="20">
              <path d="M512 2C230.2 2 2 230.2 2 512s228.2 510 510 510 510-228.2 510-510S793.3 2 512 2z m235.9 442c-1 4.6-3.6 10.8-7.2 19.1l-0.5 0.5c-21.6 45.8-77.3 135.5-77.3 135.5l-0.5-0.5-16.5 28.3h78.8L574.3 826.8l34-136h-61.8l21.6-90.2c-17.5 4.1-38.1 9.8-62.3 18 0 0-33 19.1-94.8-37.1 0 0-41.7-37.1-17.5-45.8 10.3-4.1 50-8.8 81.4-12.9 42.2-5.7 68.5-8.8 68.5-8.8s-130.3 2.1-161.2-3.1c-30.9-4.6-70.1-56.7-78.3-102 0 0-12.9-24.7 27.8-12.9 40.2 11.8 209.2 45.8 209.2 45.8S321.4 375 307 358.5c-14.4-16.5-42.8-89.6-39.2-134.5 0 0 1.5-11.3 12.9-8.2 0 0 161.8 74.2 272.5 114.4C664.5 371.4 760.8 392 747.9 444z" fill="#ffffff"/>
            </svg>
          </span>
          <span className="DingtalkLoginButton-text">
            {isDingtalkEnv
              ? app.translator.trans('jiushutech-dingtalk-login.forum.login.button_h5')
              : app.translator.trans('jiushutech-dingtalk-login.forum.login.button')}
          </span>
        </Button>
      </div>
    );
  }

  handleClick(e: Event) {
    e.preventDefault();
    
    const isDingtalkEnv = this.isDingtalkEnvironment();
    console.log('[DingTalk LoginButton] 点击登录按钮，钉钉环境:', isDingtalkEnv);
    
    if (isDingtalkEnv) {
      this.handleH5Login();
    } else {
      this.handleQRCodeLogin();
    }
  }

  handleQRCodeLogin() {
    console.log('[DingTalk LoginButton] 使用扫码登录');
    // 打开钉钉授权页面
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    const authUrl = app.forum.attribute('baseUrl') + '/auth/dingtalk';
    console.log('[DingTalk LoginButton] 授权URL:', authUrl);
    
    const popup = window.open(
      authUrl,
      'dingtalk_login',
      `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );

    // 监听弹窗关闭
    const checkClosed = setInterval(() => {
      if (popup && popup.closed) {
        clearInterval(checkClosed);
        // 关闭登录弹窗并刷新页面
        app.modal.close();
        window.location.reload();
      }
    }, 500);
  }

  async handleH5Login() {
    console.log('[DingTalk LoginButton] 使用H5登录');
    this.loading = true;
    m.redraw();

    try {
      // 检查是否有钉钉JSAPI
      if (typeof (window as any).dd === 'undefined') {
        console.log('[DingTalk LoginButton] 加载钉钉JSAPI...');
        // 动态加载钉钉JSAPI
        await this.loadDingtalkJSAPI();
      }

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');
      console.log('[DingTalk LoginButton] CorpId:', corpId);

      if (!corpId) {
        console.error('[DingTalk LoginButton] CorpId未配置');
        this.loading = false;
        m.redraw();
        app.alerts.show({ type: 'error' }, '钉钉CorpId未配置，请联系管理员');
        return;
      }

      dd.ready(() => {
        console.log('[DingTalk LoginButton] 钉钉JSAPI就绪，请求授权码...');
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            console.log('[DingTalk LoginButton] 获取授权码成功');
            try {
              const response = await app.request({
                method: 'POST',
                url: app.forum.attribute('apiUrl') + '/dingtalk/h5-login',
                body: { code: result.code },
              });

              if (response.success) {
                console.log('[DingTalk LoginButton] 登录成功');
                // 关闭登录弹窗并刷新页面
                app.modal.close();
                window.location.reload();
              } else {
                console.error('[DingTalk LoginButton] 登录失败:', response.message);
                app.alerts.show({ type: 'error' }, response.message || '登录失败');
              }
            } catch (error: any) {
              console.error('[DingTalk LoginButton] 登录请求失败:', error);
              app.alerts.show({ type: 'error' }, error.message || '登录失败');
            } finally {
              this.loading = false;
              m.redraw();
            }
          },
          onFail: (err: any) => {
            console.error('[DingTalk LoginButton] 获取授权码失败:', err);
            this.loading = false;
            m.redraw();
            app.alerts.show({ type: 'error' }, '获取授权码失败：' + (err.message || JSON.stringify(err)));
          },
        });
      });

      dd.error((err: any) => {
        console.error('[DingTalk LoginButton] JSAPI错误:', err);
        this.loading = false;
        m.redraw();
        app.alerts.show({ type: 'error' }, '钉钉JSAPI错误：' + (err.message || JSON.stringify(err)));
      });
    } catch (error: any) {
      console.error('[DingTalk LoginButton] 初始化失败:', error);
      this.loading = false;
      m.redraw();
      app.alerts.show({ type: 'error' }, error.message || '初始化失败');
    }
  }

  loadDingtalkJSAPI(): Promise<void> {
    return new Promise((resolve, reject) => {
      // 检查是否已加载
      if (typeof (window as any).dd !== 'undefined') {
        console.log('[DingTalk LoginButton] JSAPI已加载');
        resolve();
        return;
      }

      console.log('[DingTalk LoginButton] 动态加载JSAPI脚本...');
      const script = document.createElement('script');
      script.src = 'https://g.alicdn.com/dingding/dingtalk-jsapi/3.0.12/dingtalk.open.js';
      script.onload = () => {
        console.log('[DingTalk LoginButton] JSAPI脚本加载完成');
        resolve();
      };
      script.onerror = () => {
        console.error('[DingTalk LoginButton] JSAPI脚本加载失败');
        reject(new Error('Failed to load DingTalk JSAPI'));
      };
      document.head.appendChild(script);
    });
  }
}
