/**
 * Pixel Scout admin app entry point.
 *
 * Wires React Query, TanStack Router (hash history) and the App shell into the
 * `#ps-root` container injected by `includes/admin/class-admin-page.php`.
 * Defines the four hash routes: `/` → redirect, `/dashboard`, `/duplicates`,
 * `/tools`.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
	createRootRoute,
	createRoute,
	createRouter,
	RouterProvider,
	createHashHistory,
	redirect,
} from '@tanstack/react-router';
import App from './App';
import Dashboard from './components/Dashboard';
import Duplicates from './components/Duplicates';
import Tools from './components/Tools';
import Settings from './components/Settings';
import './styles/globals.css';

const rootRoute = createRootRoute({ component: App });

const indexRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/',
	beforeLoad: () => {
		throw redirect({ to: '/dashboard' });
	},
});

const dashboardRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/dashboard',
	component: Dashboard,
});

const duplicatesRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/duplicates',
	component: Duplicates,
});

const toolsRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/tools',
	component: Tools,
});

const settingsRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/settings',
	component: Settings,
});

const routeTree = rootRoute.addChildren([
	indexRoute,
	dashboardRoute,
	duplicatesRoute,
	toolsRoute,
	settingsRoute,
]);

const router = createRouter({
	routeTree,
	history: createHashHistory(),
});

declare module '@tanstack/react-router' {
	interface Register {
		router: typeof router;
	}
}

const container = document.getElementById('ps-root');
if (container) {
	const queryClient = new QueryClient({
		defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
	});
	createRoot(container).render(
		<React.StrictMode>
			<QueryClientProvider client={queryClient}>
				<RouterProvider router={router} />
			</QueryClientProvider>
		</React.StrictMode>
	);
}
