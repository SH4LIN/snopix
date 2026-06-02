import { withResponsive } from '../lib/with-responsive';
import DashboardDesktop from './DashboardDesktop';
import DashboardMobile from './mobile/DashboardMobile';

export default withResponsive(DashboardDesktop, DashboardMobile, 'Dashboard');
