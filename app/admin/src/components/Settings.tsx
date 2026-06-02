import { withResponsive } from '../lib/with-responsive';
import SettingsDesktop from './SettingsDesktop';
import SettingsMobile from './mobile/SettingsMobile';

export default withResponsive(SettingsDesktop, SettingsMobile, 'Settings');
