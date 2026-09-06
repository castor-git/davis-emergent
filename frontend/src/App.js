import { useEffect, useState } from "react";
import "@/App.css";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import axios from "axios";
import { HOME } from "@/constants/testIds";

const API = `${process.env.REACT_APP_BACKEND_URL}/api`;

const Home = () => {
  const [status, setStatus] = useState("");

  useEffect(() => {
    let cancelled = false;
    axios
      .get(`${API}/health`)
      .then((response) => !cancelled && setStatus(response.data.ok ? "API online" : "API degraded"))
      .catch(() => !cancelled && setStatus("API unreachable"));
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div>
      <header className="App-header">
        <a
          data-testid={HOME.emergentLink}
          className="App-link"
          href="https://emergent.sh"
          target="_blank"
          rel="noopener noreferrer"
        >
          <img
            alt="Emergent"
            src="https://avatars.githubusercontent.com/in/1201222?s=120&u=2686cf91179bbafbc7a71bfbc43004cf9ae1acea&v=4"
          />
        </a>
        <p className="mt-5">Building something incredible ~!</p>
        {status && <p data-testid="api-status">{status}</p>}
      </header>
    </div>
  );
};

function App() {
  return (
    <div className="App">
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Home />}>
            <Route index element={<Home />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </div>
  );
}

export default App;
