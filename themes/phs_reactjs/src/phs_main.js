import React, { useState } from 'react';
import ErrorBoundary from "./js/ErrorBoundary";

export default function PHSMainApp() {
  // Declare a new state variable, which we'll call "count"
  const [count, setCount] = useState(0);

  return (
      <div>
      <p>You clicked {count} times</p>
  <button onClick={() => setCount(count + 1)}>
  Click me
  </button>
  </div>
);
}

const wrapper = document.getElementById("phs_react_main_app");
wrapper ? ReactDOM.render("<ErrorBoundary><PHSMainApp /></ErrorBoundary>", wrapper) : false;
