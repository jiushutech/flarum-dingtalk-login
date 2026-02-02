import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class DingtalkLoginButton extends Component {
  loading: boolean = false;

  view() {
    const isDingtalkEnv = /DingTalk/i.test(navigator.userAgent);

    return (
      <div className="DingtalkLoginButton">
        <div className="DingtalkLoginButton-divider">
          <span>{app.translator.trans('jiushutech-dingtalk-login.forum.login.or')}</span>
        </div>
        <Button
          className="Button Button--block DingtalkLoginButton-button"
          icon="fas fa-comment-dots"
          loading={this.loading}
          onclick={this.handleClick.bind(this)}
        >
          <span className="DingtalkLoginButton-icon">
            <svg viewBox="0 0 1024 1024" width="20" height="20">
              <path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm227 385.3c-1 1.7-2.1 3.3-3.2 4.9-9.4 14.1-20.6 26.6-33.4 37.5-12.8 10.9-27.1 20.1-42.7 27.5-15.6 7.4-32.4 13-50.1 16.7-17.7 3.7-36.2 5.6-55.3 5.6h-0.1c-19.1 0-37.6-1.9-55.3-5.6-17.7-3.7-34.5-9.3-50.1-16.7-15.6-7.4-29.9-16.6-42.7-27.5-12.8-10.9-24-23.4-33.4-37.5-1.1-1.6-2.2-3.2-3.2-4.9-0.5-0.8-0.9-1.6-1.4-2.4-8.5-14.8-14.8-30.9-18.7-47.9-3.9-17-5.9-34.8-5.9-53.1 0-18.3 2-36.1 5.9-53.1 3.9-17 10.2-33.1 18.7-47.9 0.5-0.8 0.9-1.6 1.4-2.4 1-1.7 2.1-3.3 3.2-4.9 9.4-14.1 20.6-26.6 33.4-37.5 12.8-10.9 27.1-20.1 42.7-27.5 15.6-7.4 32.4-13 50.1-16.7 17.7-3.7 36.2-5.6 55.3-5.6h0.1c19.1 0 37.6 1.9 55.3 5.6 17.7 3.7 34.5 9.3 50.1 16.7 15.6 7.4 29.9 16.6 42.7 27.5 12.8 10.9 24 23.4 33.4 37.5 1.1 1.6 2.2 3.2 3.2 4.9 0.5 0.8 0.9 1.6 1.4 2.4 8.5 14.8 14.8 30.9 18.7 47.9 3.9 17 5.9 34.8 5.9 53.1 0 18.3-2 36.1-5.9 53.1-3.9 17-10.2 33.1-18.7 47.9-0.5 0.8-0.9 1.6-1.4 2.4z" fill="#007FFF"/>
            </svg>
          </span>
          {isDingtalkEnv
            ? app.translator.trans('jiushutech-dingtalk-login.forum.login.button_h5')
            : app.translator.trans('jiushutech-dingtalk-login.forum.login.button')}
        </Button>
      </div>
    );
  }

  handleClick(e: Event) {
    e.preventDefault();
    
    const isDingtalkEnv = /DingTalk/i.test(navigator.userAgent);
    
    if (isDingtalkEnv) {
      this.handleH5Login();
    } else {
      this.handleQRCodeLogin();
    }
  }

  handleQRCodeLogin() {
    // 打开钉钉授权页面
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    const authUrl = app.forum.attribute('baseUrl') + '/auth/dingtalk';
    
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
    this.loading = true;
    m.redraw();

    try {
      // 检查是否有钉钉JSAPI
      if (typeof (window as any).dd === 'undefined') {
        // 动态加载钉钉JSAPI
        await this.loadDingtalkJSAPI();
      }

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');

      dd.ready(() => {
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            try {
              const response = await app.request({
                method: 'POST',
                url: app.forum.attribute('apiUrl') + '/dingtalk/h5-login',
                body: { code: result.code },
              });

              if (response.success) {
                // 关闭登录弹窗并刷新页面
                app.modal.close();
                window.location.reload();
              } else {
                app.alerts.show({ type: 'error' }, response.message || '登录失败');
              }
            } catch (error: any) {
              app.alerts.show({ type: 'error' }, error.message || '登录失败');
            } finally {
              this.loading = false;
              m.redraw();
            }
          },
          onFail: (err: any) => {
            this.loading = false;
            m.redraw();
            app.alerts.show({ type: 'error' }, '获取授权码失败：' + (err.message || JSON.stringify(err)));
          },
        });
      });
    } catch (error: any) {
      this.loading = false;
      m.redraw();
      app.alerts.show({ type: 'error' }, error.message || '初始化失败');
    }
  }

  loadDingtalkJSAPI(): Promise<void> {
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://g.alicdn.com/dingding/dingtalk-jsapi/3.0.12/dingtalk.open.js';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load DingTalk JSAPI'));
      document.head.appendChild(script);
    });
  }
}
