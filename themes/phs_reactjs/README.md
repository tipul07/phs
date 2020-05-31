### Currently suspended

Idea of integrating React in PHS is suspended atm utill I find a better JavaScript compiler which handles embeded PHP code in JS files. Maybe Grunt?! Webpack wasted a weekend already.

### Installation

If you want to use this theme, first run ``npm update`` in this directory.

### Developing

When developing React.js components in your plugin ``templates`` directory create a ``react/js`` directory where you put all your JSX enabled scripts.
All JavaScript (JSX enabled) will get compiled and bundled in theme's ``phs_react`` directory using [webpack](https://webpack.js.org/).

### Using JS components in your views
