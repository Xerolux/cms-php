import React from 'react';
import { Breadcrumb as AntBreadcrumb } from 'antd';
import { HomeOutlined } from '@ant-design/icons';
import { useLocation, Link } from 'react-router-dom';

interface BreadcrumbItem {
  path: string;
  name: string;
}

const Breadcrumb: React.FC = () => {
  const location = useLocation();

  const getBreadcrumbs = (): BreadcrumbItem[] => {
    const pathnames = location.pathname.split('/').filter((x) => x);

    const breadcrumbs: BreadcrumbItem[] = [
      { path: '/', name: 'Home' },
    ];

    // Build breadcrumb items
    let currentPath = '';
    for (let i = 0; i < pathnames.length; i++) {
      currentPath += `/${pathnames[i]}`;
      const name = pathnames[i]
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

      breadcrumbs.push({
        path: currentPath,
        name: name,
      });
    }

    return breadcrumbs;
  };

  const breadcrumbs = getBreadcrumbs();

  if (breadcrumbs.length <= 1) {
    return null; // Don't show on home page
  }

  const itemRender = (
    route: any,
    params: any,
    routes: any[],
    paths: string[]
  ) => {
    const isLast = routes.indexOf(route) === routes.length - 1;
    return isLast ? (
      <span>{route.name}</span>
    ) : (
      <Link to={route.path}>{route.name}</Link>
    );
  };

  return (
    <AntBreadcrumb
      style={{ margin: '16px 0' }}
      itemRender={itemRender}
      items={breadcrumbs.map((item) => ({
        title: item.name,
        path: item.path,
      }))}
    />
  );
};

export default Breadcrumb;
