import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class DingtalkSettingsPage extends ExtensionPage {
  loading: boolean = false;
  activeTab: string = 'basic';
  logs: any[] = [];
  logsLoading: boolean = false;
  logsMeta: any = {};

  oninit(vnode: any) {
    super.oninit(vnode);
  }

  content() {
    return (
      <div className="DingtalkSettingsPage">
        <div className="DingtalkSettingsPage-header">
          <h2>
            <i className="fas fa-comment-dots"></i>
            <span className="DingtalkSettingsPage-title">{app.translator.trans('jiushutech-dingtalk-login.admin.title')}</span>
          </h2>
          <p className="DingtalkSettingsPage-description">
            {app.translator.trans('jiushutech-dingtalk-login.admin.description')}
          </p>
        </div>

        <div className="DingtalkSettingsPage-tabs">
          <button
            className={'DingtalkSettingsPage-tab' + (this.activeTab === 'basic' ? ' active' : '')}
            onclick={() => { this.activeTab = 'basic'; m.redraw(); }}
          >
            <i className="fas fa-cog"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.tabs.basic')}</span>
          </button>
          <button
            className={'DingtalkSettingsPage-tab' + (this.activeTab === 'login' ? ' active' : '')}
            onclick={() => { this.activeTab = 'login'; m.redraw(); }}
          >
            <i className="fas fa-sign-in-alt"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.tabs.login')}</span>
          </button>
          <button
            className={'DingtalkSettingsPage-tab' + (this.activeTab === 'sync' ? ' active' : '')}
            onclick={() => { this.activeTab = 'sync'; m.redraw(); }}
          >
            <i className="fas fa-sync"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.tabs.sync')}</span>
          </button>
          <button
            className={'DingtalkSettingsPage-tab' + (this.activeTab === 'logs' ? ' active' : '')}
            onclick={() => { this.activeTab = 'logs'; this.loadLogs(); m.redraw(); }}
          >
            <i className="fas fa-list"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.tabs.logs')}</span>
          </button>
        </div>

        <div className="DingtalkSettingsPage-content">
          {this.activeTab === 'basic' && this.renderBasicSettings()}
          {this.activeTab === 'login' && this.renderLoginSettings()}
          {this.activeTab === 'sync' && this.renderSyncSettings()}
          {this.activeTab === 'logs' && this.renderLogsTab()}
        </div>
      </div>
    );
  }

  renderBasicSettings() {
    return (
      <div className="DingtalkSettingsPage-section">
        <div className="DingtalkSettingsPage-sectionHeader">
          <h3>
            <i className="fas fa-key"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.basic.api_config')}</span>
          </h3>
          <p className="DingtalkSettingsPage-sectionHelp">
            {app.translator.trans('jiushutech-dingtalk-login.admin.basic.api_config_help')}
          </p>
        </div>

        <div className="DingtalkSettingsPage-formGrid">
          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.app_key')}
            </label>
            <input
              className="FormControl"
              type="text"
              bidi={this.setting('jiushutech-dingtalk-login.app_key')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.basic.app_key_placeholder'))}
            />
          </div>

          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.app_secret')}
            </label>
            <input
              className="FormControl"
              type="password"
              bidi={this.setting('jiushutech-dingtalk-login.app_secret')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.basic.app_secret_placeholder'))}
            />
          </div>

          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.agent_id')}
            </label>
            <input
              className="FormControl"
              type="text"
              bidi={this.setting('jiushutech-dingtalk-login.agent_id')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.basic.agent_id_placeholder'))}
            />
            <p className="DingtalkSettingsPage-fieldHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.agent_id_help')}
            </p>
          </div>

          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.corp_id')}
            </label>
            <input
              className="FormControl"
              type="text"
              bidi={this.setting('jiushutech-dingtalk-login.corp_id')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.basic.corp_id_placeholder'))}
            />
            <p className="DingtalkSettingsPage-fieldHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.corp_id_help')}
            </p>
          </div>

          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.callback_url')}
            </label>
            <input
              className="FormControl"
              type="text"
              bidi={this.setting('jiushutech-dingtalk-login.callback_url')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.basic.callback_url_placeholder'))}
            />
            <p className="DingtalkSettingsPage-fieldHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.basic.callback_url_help')}
            </p>
          </div>
        </div>

        {this.submitButton()}
      </div>
    );
  }

  renderLoginSettings() {
    return (
      <div className="DingtalkSettingsPage-section">
        <div className="DingtalkSettingsPage-sectionHeader">
          <h3>
            <i className="fas fa-shield-alt"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.login.control')}</span>
          </h3>
        </div>

        <div className="DingtalkSettingsPage-switchGroup">
          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.force_bind')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.force_bind')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.login.force_bind')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.force_bind_help')}
            </p>
          </div>

          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.only_dingtalk_login')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.only_dingtalk_login')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.login.only_dingtalk')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.only_dingtalk_help')}
            </p>
          </div>

          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.auto_register')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.auto_register')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.login.auto_register')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.auto_register_help')}
            </p>
          </div>
        </div>

        <div className="DingtalkSettingsPage-formGrid">
          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.exempt_users')}
            </label>
            <input
              className="FormControl"
              type="text"
              bidi={this.setting('jiushutech-dingtalk-login.exempt_users')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.login.exempt_users_placeholder'))}
            />
            <p className="DingtalkSettingsPage-fieldHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.exempt_users_help')}
            </p>
          </div>
        </div>

        <div className="DingtalkSettingsPage-switchGroup">
          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.enable_h5_login')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.enable_h5_login')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.login.enable_h5')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.enable_h5_help')}
            </p>
          </div>

          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.show_on_index')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.show_on_index')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.login.show_on_index')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.login.show_on_index_help')}
            </p>
          </div>
        </div>

        {this.submitButton()}
      </div>
    );
  }

  renderSyncSettings() {
    return (
      <div className="DingtalkSettingsPage-section">
        <div className="DingtalkSettingsPage-sectionHeader">
          <h3>
            <i className="fas fa-user-cog"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.sync.title')}</span>
          </h3>
        </div>

        <div className="DingtalkSettingsPage-switchGroup">
          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.sync_nickname')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.sync_nickname')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.sync.nickname')}
              </span>
            </Switch>
          </div>

          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.sync_avatar')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.sync_avatar')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.sync.avatar')}
              </span>
            </Switch>
          </div>

          {/* 同步手机号和邮箱功能暂时隐藏，此版本不启用
          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.sync_mobile')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.sync_mobile')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.sync.mobile')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.sync.mobile_help')}
            </p>
          </div>

          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.sync_email')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.sync_email')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.sync.email')}
              </span>
            </Switch>
            <p className="DingtalkSettingsPage-switchHelp">
              {app.translator.trans('jiushutech-dingtalk-login.admin.sync.email_help')}
            </p>
          </div>
          */}
        </div>

        <div className="Form-group DingtalkSettingsPage-selectGroup">
          <label className="DingtalkSettingsPage-label">
            {app.translator.trans('jiushutech-dingtalk-login.admin.sync.username_rule')}
          </label>
          <div className="DingtalkSettingsPage-selectWrapper">
            <Select
              value={this.setting('jiushutech-dingtalk-login.username_rule')() || 'nickname'}
              options={{
                nickname: app.translator.trans('jiushutech-dingtalk-login.admin.sync.username_rule_nickname'),
                random: app.translator.trans('jiushutech-dingtalk-login.admin.sync.username_rule_random'),
              }}
              onchange={(value: string) => this.setting('jiushutech-dingtalk-login.username_rule')(value)}
            />
          </div>
        </div>

        {this.submitButton()}
      </div>
    );
  }

  renderLogsTab() {
    return (
      <div className="DingtalkSettingsPage-section DingtalkSettingsPage-logs">
        {/* 日志设置 */}
        <div className="DingtalkSettingsPage-sectionHeader">
          <h3>
            <i className="fas fa-cog"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.sync.log_settings')}</span>
          </h3>
        </div>

        <div className="DingtalkSettingsPage-formGrid">
          <div className="Form-group">
            <label className="DingtalkSettingsPage-label">
              {app.translator.trans('jiushutech-dingtalk-login.admin.sync.log_retention_days')}
            </label>
            <input
              className="FormControl"
              type="number"
              min="1"
              max="365"
              bidi={this.setting('jiushutech-dingtalk-login.log_retention_days')}
              placeholder={String(app.translator.trans('jiushutech-dingtalk-login.admin.sync.log_retention_days_placeholder'))}
            />
          </div>
        </div>

        <div className="DingtalkSettingsPage-switchGroup">
          <div className="DingtalkSettingsPage-switchItem">
            <Switch
              state={this.setting('jiushutech-dingtalk-login.allow_log_export')() === '1'}
              onchange={(value: boolean) => this.setting('jiushutech-dingtalk-login.allow_log_export')(value ? '1' : '0')}
            >
              <span className="DingtalkSettingsPage-switchLabel">
                {app.translator.trans('jiushutech-dingtalk-login.admin.sync.allow_log_export')}
              </span>
            </Switch>
          </div>
        </div>

        {this.submitButton()}

        {/* 登录日志列表 */}
        <div className="DingtalkSettingsPage-sectionHeader" style={{ marginTop: '30px' }}>
          <h3>
            <i className="fas fa-list-alt"></i>
            <span>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.title')}</span>
          </h3>
        </div>

        <div className="DingtalkSettingsPage-logsToolbar">
          <Button
            className="Button Button--primary"
            icon="fas fa-sync"
            onclick={() => this.loadLogs()}
            loading={this.logsLoading}
          >
            {app.translator.trans('jiushutech-dingtalk-login.admin.logs.refresh')}
          </Button>
          <Button
            className="Button"
            icon="fas fa-download"
            onclick={() => this.exportLogs()}
          >
            {app.translator.trans('jiushutech-dingtalk-login.admin.logs.export')}
          </Button>
        </div>

        {this.logsLoading ? (
          <div className="DingtalkSettingsPage-logsLoading">
            <LoadingIndicator />
          </div>
        ) : (
          <div className="DingtalkSettingsPage-logsTable">
            <table className="Table">
              <thead>
                <tr>
                  <th>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.time')}</th>
                  <th>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.user')}</th>
                  <th>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.type')}</th>
                  <th>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.status')}</th>
                  <th>{app.translator.trans('jiushutech-dingtalk-login.admin.logs.ip')}</th>
                </tr>
              </thead>
              <tbody>
                {this.logs.length === 0 ? (
                  <tr>
                    <td colspan="5" className="DingtalkSettingsPage-logsEmpty">
                      {app.translator.trans('jiushutech-dingtalk-login.admin.logs.empty')}
                    </td>
                  </tr>
                ) : (
                  this.logs.map((log: any) => (
                    <tr key={log.id}>
                      <td>{new Date(log.createdAt).toLocaleString()}</td>
                      <td>{log.username || '-'}</td>
                      <td>
                        <span className={'DingtalkSettingsPage-logType DingtalkSettingsPage-logType--' + log.loginType}>
                          {this.getLoginTypeLabel(log.loginType)}
                        </span>
                      </td>
                      <td>
                        <span className={'DingtalkSettingsPage-logStatus DingtalkSettingsPage-logStatus--' + log.status}>
                          {log.status === 'success' 
                            ? app.translator.trans('jiushutech-dingtalk-login.admin.logs.status_success')
                            : app.translator.trans('jiushutech-dingtalk-login.admin.logs.status_failed')}
                        </span>
                      </td>
                      <td>{log.loginIp}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    );
  }

  getLoginTypeLabel(type: string): string {
    const labels: { [key: string]: string } = {
      scan: String(app.translator.trans('jiushutech-dingtalk-login.admin.logs.type_scan')),
      h5: String(app.translator.trans('jiushutech-dingtalk-login.admin.logs.type_h5')),
      redirect: String(app.translator.trans('jiushutech-dingtalk-login.admin.logs.type_redirect')),
    };
    return labels[type] || type;
  }

  async loadLogs() {
    this.logsLoading = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/dingtalk/login-logs',
      });

      this.logs = response.data || [];
      this.logsMeta = response.meta || {};
    } catch (error) {
      console.error('Failed to load logs:', error);
      this.logs = [];
    } finally {
      this.logsLoading = false;
      m.redraw();
    }
  }

  async exportLogs() {
    try {
      // 使用原生 fetch 来处理文件下载
      const response = await fetch(app.forum.attribute('apiUrl') + '/dingtalk/logs-export', {
        method: 'GET',
        headers: {
          'Authorization': `Token ${app.session.csrfToken}`,
        },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error('Export failed');
      }

      // 获取 blob 数据
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `dingtalk-login-logs-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Failed to export logs:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('jiushutech-dingtalk-login.admin.logs.export_failed'));
    }
  }
}
