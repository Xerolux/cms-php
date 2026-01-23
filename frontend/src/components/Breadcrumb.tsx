import React from 'react';
import { Breadcrumb as AntBreadcrumb } from 'antd';
import { useLocation, Link } from 'react-router-dom';

interface BreadcrumbItem {
  path: string;
  name: string;
}

interface AntBreadcrumbItem {
  title: string;
  path: string;
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
    route: AntBreadcrumbItem,
    _params: unknown,
    routes: AntBreadcrumbItem[]
  ) => {
    const isLast = routes.indexOf(route) === routes.length - 1;
    return isLast ? (
      <span>{route.title}</span>
    ) : (
      <Link to={route.path}>{route.title}</Link>
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
