import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class DingtalkBindRequiredModal extends Modal {
  loading: boolean = false;

  className() {
    return 'DingtalkBindRequiredModal Modal--small';
  }

  title() {
    return app.translator.trans('jiushutech-dingtalk-login.forum.bind_required.title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="DingtalkBindRequired-content">
          <div className="DingtalkBindRequired-icon">
            <i className="fas fa-link"></i>
          </div>
          <p className="DingtalkBindRequired-message">
            {app.translator.trans('jiushutech-dingtalk-login.forum.bind_required.message')}
          </p>
          <Button
            className="Button Button--primary Button--block"
            onclick={this.handleBind.bind(this)}
            loading={this.loading}
          >
            {app.translator.trans('jiushutech-dingtalk-login.forum.bind_required.button')}
          </Button>
        </div>
      </div>
    );
  }

  handleBind() {
    const isDingtalkEnv = /DingTalk/i.test(navigator.userAgent);
    
    if (isDingtalkEnv) {
      this.handleH5Bind();
    } else {
      this.handleQRCodeBind();
    }
  }

  handleQRCodeBind() {
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    const authUrl = app.forum.attribute('baseUrl') + '/auth/dingtalk?bind=1';
    
    const popup = window.open(
      authUrl,
      'dingtalk_bind',
      `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );

    // 监听绑定完成
    const checkClosed = setInterval(() => {
      if (popup?.closed) {
        clearInterval(checkClosed);
        // 刷新页面检查绑定状态
        window.location.reload();
      }
    }, 500);
  }

  async handleH5Bind() {
    this.loading = true;
    m.redraw();

    try {
      if (typeof (window as any).dd === 'undefined') {
        await this.loadDingtalkJSAPI();
      }

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');

      dd.ready(() => {
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            await this.submitBindCode(result.code);
          },
          onFail: (err: any) => {
            this.loading = false;
            m.redraw();
            app.alerts.show({ type: 'error' }, '获取授权码失败');
          },
        });
      });
    } catch (error: any) {
      this.loading = false;
      m.redraw();
      app.alerts.show({ type: 'error' }, error.message || '初始化失败');
    }
  }

  async submitBindCode(code: string) {
    try {
      const response = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/dingtalk/bind',
        body: { code, type: 'h5' },
      });

      if (response.success) {
        app.alerts.show({ type: 'success' }, app.translator.trans('jiushutech-dingtalk-login.forum.settings.bind_success'));
        this.hide();
        window.location.reload();
      } else {
        app.alerts.show({ type: 'error' }, response.message || '绑定失败');
      }
    } catch (error: any) {
      app.alerts.show({ type: 'error' }, error.message || '绑定失败');
    } finally {
      this.loading = false;
      m.redraw();
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

  // 禁止关闭弹窗
  isDismissible() {
    return false;
  }
}
