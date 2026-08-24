import { NavMenu } from '@shopify/app-bridge-react';
import { Link, Outlet } from 'react-router-dom';

export default function AppLayout() {
  return (
    <>
      <NavMenu>
        <Link to="/" rel="home">
          Overview
        </Link>
        <Link to="/pixels">
          Pixels
        </Link>
        <Link to="/analytics">
          Analytics
        </Link>
        <Link to="/event-logs">
          Event Logs
        </Link>
        <Link to="/setup-guide">
          Setup Guide
        </Link>
        <Link to="/settings">
          Settings
        </Link>
        <Link to="/plan">
          Plans
        </Link>
        <Link to="/setup-guide">
          Setup guide
        </Link>
      </NavMenu>

      <Outlet />
    </>
  );
}