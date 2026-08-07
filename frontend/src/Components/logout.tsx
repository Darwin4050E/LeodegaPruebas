import React from "react";
import { useNavigate } from "react-router-dom";
import api from "../api/axios";

const LogoutButton: React.FC = () => {
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await api.post("/logout", {}, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
        },
      });

      //limpiar en web 
      localStorage.removeItem("auth_token");
      localStorage.removeItem("auth_user");

      delete api.defaults.headers.common["Authorization"];
      navigate("/login", { replace: true });
      
    } catch (error) {
      console.error("Error al cerrar sesión:", error);
    }
  };

  return (
    <button
      onClick={handleLogout}
      className="mt-6 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md font-semibold transition"
    >
      Cerrar sesión
    </button>
  );
};

export default LogoutButton;
