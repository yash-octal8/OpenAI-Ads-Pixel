import React, { useEffect } from 'react';
import { NavMenu } from '@shopify/app-bridge-react';
import { Link, Outlet, useNavigate, useLocation } from 'react-router-dom';
import api from '../api';

export default function AppLayout() {
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    checkPlanRedirect();
  }, [location.pathname]);

  const checkPlanRedirect = async () => {
    try {
      const res = await api.get('/plan');
      if (res.data.success && !res.data.currentPlan) {
        if (location.pathname !== '/plan') {
          navigate('/plan');
        }
      }
    } catch (e) {
      console.error('Plan check redirect error:', e);
    }
  };

  return (
    <>
      <NavMenu>
        <Link to="/" rel="home">
          Performance
        </Link>
        <Link to="/settings">
          Settings
        </Link>
        <Link to="/plan">
          Plans
        </Link>
      </NavMenu>

      <Outlet />
    </>
  );
}