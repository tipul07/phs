const path = require('path');
const glob = require("glob");
const HtmlWebPackPlugin = require("html-webpack-plugin");
const PhpOutputPlugin = require('webpack-php-output');

function getPHSRactiveEntries()
{
    var plugins_path = path.resolve( __dirname, "../../" ) + "/plugins/";
    var src_arr = glob.sync("./src/*.js");

    var plugins_arr = glob.sync( plugins_path + "/**/templates/react/js/**/**/*.js" );

    if( typeof plugins_arr === "object"
     && plugins_arr.length )
        src_arr = src_arr.concat( plugins_arr );

    return src_arr;
}

module.exports = {
    entry: getPHSRactiveEntries(), //path.resolve( __dirname, "./src/phs_main.js" ),
    output: {
        path: path.resolve( __dirname, './phs_react' ),
        filename: '[name].js'
    },
    externals : {
        react: 'React',
        "react-dom": 'ReactDOM',
        jquery: 'jQuery',
        phs_app_initial_state: 'phs_app_initial_state'
    },
    devtool: "source-map",
    module: {
        rules: [
            {
                enforce: "pre",
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: "eslint-loader"
                    }
                ]
            },
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: "babel-loader",
                        options: {
                            presets: [
                                "@babel/env",
                                "@babel/react"
                            ]
                        }
                    }
                ]
            },
            {
                test: /\.html$/,
                use: [
                    {
                        loader: "html-loader"
                    }
                ]
            },
            {
                test: /\.php$/,
                use: [
                    {
                        loader: "html-loader",
                        options: {
                            attributes: {
                                root: '<?php echo "Inside"?>',
                            }
                        }
                    }
                ]
            },
            {
                test: /\.css$/,
                use: [
                    "style-loader",
                    "css-loader"
                ]
            }
        ]
    },
    plugins: [
        new HtmlWebPackPlugin({
            inject: false,
            template: "./src/phs_main_app.php",
            filename: "./phs_main_app.php"
        }),
        new PhpOutputPlugin({})
    ]

};
