import { RouterProvider } from "react-router-dom";
import { AuthProvider } from "./auth/AuthContext";
import { appRouter } from "./router";

function App() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get("api_token");

  if (token) {
    localStorage.setItem("api_token", token);

    localStorage.setItem(
      "api_user",
      JSON.stringify({
        id: 0,
        name: "Google User",
        email: "",
        role: "User",
        permissions: [],
      })
    );

    window.history.replaceState({}, document.title, "/dashboard");
  }

  return (
    <AuthProvider>
      <RouterProvider router={appRouter} />
    </AuthProvider>
  );
}

export default App;