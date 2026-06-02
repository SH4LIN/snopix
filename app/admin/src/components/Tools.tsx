import { withResponsive } from '../lib/with-responsive';
import ToolsDesktop from './ToolsDesktop';
import ToolsMobile from './mobile/ToolsMobile';

export default withResponsive(ToolsDesktop, ToolsMobile, 'Tools');
