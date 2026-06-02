import { withResponsive } from '../lib/with-responsive';
import DuplicatesDesktop from './DuplicatesDesktop';
import DuplicatesMobile from './mobile/DuplicatesMobile';

export default withResponsive(DuplicatesDesktop, DuplicatesMobile, 'Duplicates');
