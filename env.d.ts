/// <reference types="node" />

/**
 * Environment variable definitions for TypeScript.
 * Prevents "Cannot find name 'process'" errors in tests.
 */

declare const process: {
	env: {
		CI?: string;
		WORDPRESS_URL?: string;
		WORDPRESS_USER?: string;
		WORDPRESS_PASSWORD?: string;
		NODE_ENV?: 'development' | 'production' | 'test';
		[key: string]: string | undefined;
	};
};

