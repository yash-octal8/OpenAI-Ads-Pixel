import { NavMenu } from '@shopify/app-bridge-react';
import { Link, Outlet } from 'react-router-dom';

export default function AppLayout() {
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
        <Link to="/setup-guide">
          Setup guide
        </Link>
      </NavMenu>

      <Outlet />
    </>
  );
}