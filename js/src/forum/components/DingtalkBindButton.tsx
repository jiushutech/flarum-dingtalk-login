import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class DingtalkBindButton extends Component {
  loading: boolean = false;
  checking: boolean = true;
  bound: boolean = false;
  dingtalkNickname: string = '';
  dingtalkAvatar: string = '';

  oninit(vnode: any) {
    super.oninit(vnode);
    this.checkBindStatus();
  }

  async checkBindStatus() {
    this.checking = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/dingtalk/bind-status',
      });

      this.bound = response.bound;
      this.dingtalkNickname = response.dingtalk_nickname || '';
      this.dingtalkAvatar = response.dingtalk_avatar || '';
    } catch (error) {
      console.error('Failed to check bind status:', error);
      this.bound = false;
    } finally {
      this.checking = false;
      m.redraw();
    }
  }

  view() {
    if (this.checking) {
      return (
        <div className="DingtalkBindButton">
          <div className="DingtalkBindButton-loading">
            <LoadingIndicator size="small" />
          </div>
        </div>
      );
    }

    return (
      <div className="DingtalkBindButton">
        <div className="DingtalkBindButton-content">
          {this.bound ? this.renderBoundCard() : this.renderUnboundCard()}
        </div>
      </div>
    );
  }

  renderBoundCard() {
    return (
      <div className="DingtalkBindButton-card DingtalkBindButton-card--bound DingtalkBindButton-card--vertical">
        <div className="DingtalkBindButton-avatar">
          {this.dingtalkAvatar ? (
            <img src={this.dingtalkAvatar} alt="钉钉头像" />
          ) : (
            <i className="fas fa-user"></i>
          )}
        </div>
        <div className="DingtalkBindButton-info">
          <div className="DingtalkBindButton-nickname">
            {this.dingtalkNickname || '钉钉用户'}
          </div>
          <div className="DingtalkBindButton-statusText">
            <i className="fas fa-check-circle"></i>
            {app.translator.trans('jiushutech-dingtalk-login.forum.settings.bound')}
          </div>
        </div>
        <div className="DingtalkBindButton-cardActions">
          <Button
            className="Button Button--danger Button--small"
            loading={this.loading}
            onclick={this.handleUnbind.bind(this)}
          >
            <i className="fas fa-unlink"></i>
            {app.translator.trans('jiushutech-dingtalk-login.forum.settings.unbind')}
          </Button>
        </div>
      </div>
    );
  }

  renderUnboundCard() {
    return (
      <div className="DingtalkBindButton-card DingtalkBindButton-card--unbound DingtalkBindButton-card--vertical">
        <div className="DingtalkBindButton-avatar DingtalkBindButton-avatar--empty">
          <i className="fas fa-comment-dots"></i>
        </div>
        <div className="DingtalkBindButton-info">
          <div className="DingtalkBindButton-nickname">
            {app.translator.trans('jiushutech-dingtalk-login.forum.settings.not_bound')}
          </div>
          <div className="DingtalkBindButton-statusText DingtalkBindButton-statusText--warning">
            <i className="fas fa-exclamation-circle"></i>
            {app.translator.trans('jiushutech-dingtalk-login.forum.settings.bind_hint')}
          </div>
        </div>
        <div className="DingtalkBindButton-cardActions">
          <Button
            className="Button Button--primary"
            icon="fas fa-link"
            loading={this.loading}
            onclick={this.handleBind.bind(this)}
          >
            {app.translator.trans('jiushutech-dingtalk-login.forum.settings.bind')}
          </Button>
        </div>
      </div>
    );
  }

  handleBind(e: Event) {
    e.preventDefault();
    
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

    // 监听弹窗关闭
    const checkClosed = setInterval(() => {
      if (popup?.closed) {
        clearInterval(checkClosed);
        // 关闭登录弹窗并刷新页面
        app.modal.close();
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
            await this.submitBindCode(result.code, 'h5');
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

  async submitBindCode(code: string, type: string) {
    try {
      const response = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/dingtalk/bind',
        body: { code, type },
      });

      if (response.success) {
        app.alerts.show({ type: 'success' }, app.translator.trans('jiushutech-dingtalk-login.forum.settings.bind_success'));
        // 关闭弹窗并刷新页面
        app.modal.close();
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

  async handleUnbind(e: Event) {
    e.preventDefault();
    
    if (!confirm(app.translator.trans('jiushutech-dingtalk-login.forum.settings.unbind_confirm') as string)) {
      return;
    }

    this.loading = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'DELETE',
        url: app.forum.attribute('apiUrl') + '/dingtalk/unbind',
      });

      if (response.success) {
        this.bound = false;
        this.dingtalkNickname = '';
        this.dingtalkAvatar = '';
        app.alerts.show({ type: 'success' }, app.translator.trans('jiushutech-dingtalk-login.forum.settings.unbind_success'));
      } else {
        app.alerts.show({ type: 'error' }, response.message || '解绑失败');
      }
    } catch (error: any) {
      app.alerts.show({ type: 'error' }, error.message || '解绑失败');
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
}
