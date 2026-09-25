let mix = require("laravel-mix");
const {browserSync} = require("laravel-mix");
require("dotenv").config();

mix.webpackConfig({
    output: {
        publicPath: "/wp-content/plugins/disciple-tools-autolink/dist/",
    },
    // The Disciple.Tools theme ships the web component library and enqueues it
    // as the `web-components` script, which exposes the `DtWebComponents`
    // global. Treat the package as external so we always run against the
    // theme's copy instead of bundling (and drifting from) our own.
    externals: {
        "@disciple.tools/web-components": "DtWebComponents",
    },
});

mix
    .setPublicPath("dist")
    .js("magic-link/magic-link.js", "dist/magic-link.js")
    .js("admin/admin.js", "dist/admin.js")
    .sourceMaps()
    .postCss("magic-link/magic-link.css", "dist/magic-link.css")
    .browserSync({
        proxy: process.env.MIX_URL,
        files: ["dist/*.js", "dist/*.css", "magic-link/templates/**/*.php"],
    });
