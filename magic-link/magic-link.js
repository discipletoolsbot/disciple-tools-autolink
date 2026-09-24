import {loaded} from "./js/helpers";

import "./js/menu.js";
import "./js/collapse";
import "./js/church";
import "./js/church-tile";
import "./js/lazyReveal";
import "./js/churchMenu";
import "./js/church-health-field";
import "./js/church-counts";
import "./js/ajax-field";
import "./js/groups-tree";
import "./js/churches"
import "./js/submit-button"

import "@shoelace-style/shoelace/dist/themes/light.css";
import "@shoelace-style/shoelace/dist/components/tab-group/tab-group.js";
import "@shoelace-style/shoelace/dist/components/tab/tab.js";
import "@shoelace-style/shoelace/dist/components/tab-panel/tab-panel.js";


import locationField from "./js/locationField";

loaded(() => {
    document.body.classList.add("dom-loaded");

    document.querySelectorAll(".location-field").forEach(locationField);

});
