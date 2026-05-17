import type { Config } from "tailwindcss";

const config: Config = {
	content: [
		"./index.html",
		"./src/**/*.{js,ts,jsx,tsx}",
	],
	theme: {
		extend: {
			colors: {
				ps: {
					bg:      "#FFFFFF",
					surface: "#F5F5F7",
					text:    "#1D1D1F",
					muted:   "#6E6E73",
					accent:  "#0071E3",
					danger:  "#FF3B30",
					success: "#34C759",
					warning: "#FF9500",
					border:  "#E5E5EA",
				},
			},
			fontFamily: {
				sans: [
					"-apple-system",
					"BlinkMacSystemFont",
					'"SF Pro Display"',
					'"Segoe UI"',
					"sans-serif",
				],
			},
			borderRadius: {
				card:  "12px",
				input: "8px",
				pill:  "20px",
			},
			boxShadow: {
				card: "0 1px 3px rgba(0,0,0,0.08)",
			},
			transitionDuration: {
				DEFAULT: "200ms",
			},
			transitionTimingFunction: {
				DEFAULT: "ease-out",
			},
			keyframes: {
				"ps-spin": {
					"0%":   { transform: "rotate(0deg)" },
					"100%": { transform: "rotate(360deg)" },
				},
				"ps-progress": {
					"0%":   { backgroundPosition: "200% 0" },
					"100%": { backgroundPosition: "-200% 0" },
				},
			},
			animation: {
				"ps-spin":     "ps-spin 1s linear infinite",
				"ps-progress": "ps-progress 1.6s ease-in-out infinite",
			},
			scale: {
				press: "0.98",
			},
		},
	},
	plugins: [],
};

export default config;
