import React, { useEffect, useState } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  Input,
  Select,
  message,
  Popconfirm,
  Card,
  Row,
  Col,
  Statistic,
  Tooltip,
  Descriptions,
  Alert,
  Checkbox,
  Divider,
} from 'antd';
import {
  CloudDownloadOutlined,
  DownloadOutlined,
  DeleteOutlined,
  SyncOutlined,
  PlusOutlined,
  InfoCircleOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
  ClockCircleOutlined,
  DatabaseOutlined,
  FileOutlined,
  CloudUploadOutlined,
} from '@ant-design/icons';
import { backupService } from '../services/api';
import type { PaginatedResponse } from '../types';

interface Backup {
  id: number;
  name: string;
  type: string;
  status: string;
  disk: string;
  path: string;
  file_size: number;
  file_size_formatted: string;
  items_count: number;
  description?: string;
  options?: any;
  completed_at?: string;
  failed_at?: string;
  error_message?: string;
  duration?: string;
  created_at: string;
  creator?: {
    name: string;
    email: string;
  };
}

const BackupsPage: React.FC = () => {
  const [backups, setBackups] = useState<Backup[]>([]);
  const [loading, setLoading] = useState(false);
  const [stats, setStats] = useState<any>(null);

  // Create Modal
  const [createModalVisible, setCreateModalVisible] = useState(false);
  const [backupForm, setBackupForm] = useState({
    name: '',
    type: 'full',
    description: '',
  });

  // Restore Modal
  const [restoreModalVisible, setRestoreModalVisible] = useState(false);
  const [restoringBackup, setRestoringBackup] = useState<Backup | null>(null);
  const [restoreOptions, setRestoreOptions] = useState({
    restore_database: true,
    restore_files: true,
  });
  const [restoring, setRestoring] = useState(false);

  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
    total: 0,
  });

  useEffect(() => {
    fetchBackups();
    fetchStats();
  }, [pagination.current, pagination.pageSize]);

  const fetchBackups = async () => {
    setLoading(true);
    try {
      const data: PaginatedResponse<Backup> = await backupService.getAll({
        page: pagination.current,
        per_page: pagination.pageSize,
      });
      setBackups(data.data || []);
      setPagination((prev) => ({ ...prev, total: data.total || 0 }));
    } catch (error) {
      message.error('Failed to fetch backups');
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const data = await backupService.getStats();
      setStats(data);
    } catch (error) {
      console.error('Failed to fetch stats');
    }
  };

  const handleCreate = async () => {
    if (!backupForm.type) {
      message.error('Backup type is required');
      return;
    }

    setLoading(true);
    try {
      await backupService.create(backupForm);
      message.success('Backup created successfully');
      setCreateModalVisible(false);
      setBackupForm({ name: '', type: 'full', description: '' });
      fetchBackups();
      fetchStats();
    } catch (error: any) {
      message.error(error.response?.data?.error || 'Failed to create backup');
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = (id: number) => {
    backupService.download(id);
  };

  const handleRestore = async () => {
    if (!restoringBackup) return;

    setRestoring(true);
    try {
      const result = await backupService.restore(restoringBackup.id, restoreOptions);

      message.success('Backup restored successfully');

      if (result.errors && result.errors.length > 0) {
        Modal.warning({
          title: 'Restore completed with warnings',
          content: (
            <div>
              <p>The backup was restored but there were some issues:</p>
              <ul>
                {result.errors.map((error: string, index: number) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </div>
          ),
        });
      }

      setRestoreModalVisible(false);
      setRestoreOptions({ restore_database: true, restore_files: true });
    } catch (error: any) {
      message.error(error.response?.data?.error || 'Failed to restore backup');
    } finally {
      setRestoring(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await backupService.delete(id);
      message.success('Backup deleted successfully');
      fetchBackups();
      fetchStats();
    } catch (error) {
      message.error('Failed to delete backup');
    }
  };

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'default',
      creating: 'processing',
      completed: 'success',
      failed: 'error',
    };
    return colors[status] || 'default';
  };

  const getStatusIcon = (status: string) => {
    const icons: Record<string, React.ReactNode> = {
      pending: <ClockCircleOutlined />,
      creating: <SyncOutlined spin />,
      completed: <CheckCircleOutlined />,
      failed: <ExclamationCircleOutlined />,
    };
    return icons[status] || <InfoCircleOutlined />;
  };

  const getTypeColor = (type: string) => {
    const colors: Record<string, string> = {
      full: 'blue',
      database: 'green',
      files: 'orange',
    };
    return colors[type] || 'default';
  };

  const columns = [
    {
      title: 'Name',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: Backup) => (
        <Space direction="vertical" size={0}>
          <div style={{ fontWeight: 500 }}>{name}</div>
          {record.description && (
            <div style={{ fontSize: 12, color: '#999' }}>{record.description}</div>
          )}
          <div style={{ fontSize: 12, color: '#999' }}>
            {record.creator?.name} • {new Date(record.created_at).toLocaleDateString()}
          </div>
        </Space>
      ),
      sorter: (a: Backup, b: Backup) => a.name.localeCompare(b.name),
    },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => (
        <Tag color={getTypeColor(type)} icon={type === 'full' ? <CloudDownloadOutlined /> : type === 'database' ? <DatabaseOutlined /> : <FileOutlined />}>
          {type.toUpperCase()}
        </Tag>
      ),
      filters: [
        { text: 'Full', value: 'full' },
        { text: 'Database', value: 'database' },
        { text: 'Files', value: 'files' },
      ],
      onFilter: (value: any, record: Backup) => record.type === value,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag icon={getStatusIcon(status)} color={getStatusColor(status)}>
          {status.toUpperCase()}
        </Tag>
      ),
      filters: [
        { text: 'Completed', value: 'completed' },
        { text: 'Creating', value: 'creating' },
        { text: 'Failed', value: 'failed' },
      ],
      onFilter: (value: any, record: Backup) => record.status === value,
    },
    {
      title: 'Size',
      dataIndex: 'file_size_formatted',
      key: 'file_size',
      sorter: (a: Backup, b: Backup) => a.file_size - b.file_size,
    },
    {
      title: 'Items',
      dataIndex: 'items_count',
      key: 'items_count',
      render: (count: number) => count.toLocaleString(),
      sorter: (a: Backup, b: Backup) => a.items_count - b.items_count,
    },
    {
      title: 'Duration',
      key: 'duration',
      render: (_: any, record: Backup) => record.duration || '-',
    },
    {
      title: 'Created',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date: string) => new Date(date).toLocaleString(),
      sorter: (a: Backup, b: Backup) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 200,
      render: (_: any, record: Backup) => (
        <Space size="small">
          {record.status === 'completed' && (
            <>
              <Tooltip title="Download">
                <Button
                  type="text"
                  icon={<DownloadOutlined />}
                  onClick={() => handleDownload(record.id)}
                />
              </Tooltip>

              <Tooltip title="Restore">
                <Popconfirm
                  title="Restore this backup?"
                  description="This will overwrite current data. Are you sure?"
                  onConfirm={() => {
                    setRestoringBackup(record);
                    setRestoreModalVisible(true);
                  }}
                  okText="Yes"
                  cancelText="No"
                >
                  <Button type="text" icon={<CloudUploadOutlined />} style={{ color: '#52c41a' }} />
                </Popconfirm>
              </Tooltip>
            </>
          )}

          {record.status === 'failed' && (
            <Tooltip title={record.error_message}>
              <Button type="text" icon={<ExclamationCircleOutlined />} style={{ color: '#ff4d4f' }} />
            </Tooltip>
          )}

          <Popconfirm
            title="Delete this backup?"
            onConfirm={() => handleDelete(record.id)}
            okText="Yes"
            cancelText="No"
          >
            <Button type="text" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      {/* Stats Dashboard */}
      {stats && (
        <Row gutter={16} style={{ marginBottom: 16 }}>
          <Col span={6}>
            <Card>
              <Statistic title="Total Backups" value={stats.total_backups} prefix={<CloudDownloadOutlined />} />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic title="Completed" value={stats.completed_backups} valueStyle={{ color: '#52c41a' }} />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic title="Total Size" value={stats.disk_usage?.human_size || '0 B'} />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic
                title="Latest Backup"
                value={stats.latest_backup ? new Date(stats.latest_backup).toLocaleDateString() : 'Never'}
              />
            </Card>
          </Col>
        </Row>
      )}

      <Card
        title="Backup Management"
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateModalVisible(true)}>
            Create Backup
          </Button>
        }
      >
        <Table
          columns={columns}
          dataSource={backups}
          rowKey="id"
          loading={loading}
          pagination={pagination}
          onChange={(newPagination) => {
            setPagination({
              current: newPagination.current || 1,
              pageSize: newPagination.pageSize || 20,
              total: pagination.total,
            });
          }}
        />
      </Card>

      {/* Create Backup Modal */}
      <Modal
        title="Create Backup"
        open={createModalVisible}
        onOk={handleCreate}
        onCancel={() => {
          setCreateModalVisible(false);
          setBackupForm({ name: '', type: 'full', description: '' });
        }}
        confirmLoading={loading}
        okText="Create"
      >
        <Space direction="vertical" style={{ width: '100%' }} size="large">
          <div>
            <div style={{ marginBottom: 8 }}>Backup Name</div>
            <Input
              placeholder="My Backup"
              value={backupForm.name}
              onChange={(e) => setBackupForm({ ...backupForm, name: e.target.value })}
            />
            <div style={{ fontSize: 12, color: '#999', marginTop: 4 }}>
              Optional. Leave empty to auto-generate.
            </div>
          </div>

          <div>
            <div style={{ marginBottom: 8 }}>Backup Type</div>
            <Select
              style={{ width: '100%' }}
              value={backupForm.type}
              onChange={(value) => setBackupForm({ ...backupForm, type: value })}
            >
              <Select.Option value="full">
                <Space>
                  <CloudDownloadOutlined />
                  <span>Full Backup</span>
                  <span style={{ fontSize: 12, color: '#999', marginLeft: 8 }}>(Database + Files)</span>
                </Space>
              </Select.Option>
              <Select.Option value="database">
                <Space>
                  <DatabaseOutlined />
                  <span>Database Only</span>
                </Space>
              </Select.Option>
              <Select.Option value="files">
                <Space>
                  <FileOutlined />
                  <span>Files Only</span>
                </Space>
              </Select.Option>
            </Select>
          </div>

          <div>
            <div style={{ marginBottom: 8 }}>Description</div>
            <Input.TextArea
              rows={3}
              placeholder="Optional notes about this backup"
              value={backupForm.description}
              onChange={(e) => setBackupForm({ ...backupForm, description: e.target.value })}
            />
          </div>

          <Alert
            type="info"
            message="Backup Information"
            description={
              <ul style={{ margin: 0, paddingLeft: 20 }}>
                <li>Full backups include database and all application files</li>
                <li>Database backups use mysqldump with single-transaction</li>
                <li>Files are compressed in ZIP format</li>
                <li>Backups are stored in storage/app/backups</li>
                <li>Ensure sufficient disk space before creating backups</li>
              </ul>
            }
            showIcon
          />
        </Space>
      </Modal>

      {/* Restore Modal */}
      <Modal
        title="Restore Backup"
        open={restoreModalVisible}
        onOk={handleRestore}
        onCancel={() => {
          setRestoreModalVisible(false);
          setRestoringBackup(null);
          setRestoreOptions({ restore_database: true, restore_files: true });
        }}
        confirmLoading={restoring}
        okText="Restore"
        okButtonProps={{ danger: true }}
      >
        {restoringBackup && (
          <Space direction="vertical" style={{ width: '100%' }} size="large">
            <Alert
              type="warning"
              message="Warning"
              description="Restoring a backup will overwrite current data. This action cannot be undone!"
              showIcon
            />

            <Descriptions column={1} size="small">
              <Descriptions.Item label="Backup">{restoringBackup.name}</Descriptions.Item>
              <Descriptions.Item label="Type">{restoringBackup.type.toUpperCase()}</Descriptions.Item>
              <Descriptions.Item label="Created">{new Date(restoringBackup.created_at).toLocaleString()}</Descriptions.Item>
              <Descriptions.Item label="Size">{restoringBackup.file_size_formatted}</Descriptions.Item>
            </Descriptions>

            <Divider />

            <div>
              <div style={{ marginBottom: 8 }}>Restore Options:</div>
              <Space direction="vertical">
                <Checkbox
                  checked={restoreOptions.restore_database}
                  onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_database: e.target.checked })}
                  disabled={restoringBackup.type === 'files'}
                >
                  Restore Database
                </Checkbox>
                <Checkbox
                  checked={restoreOptions.restore_files}
                  onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_files: e.target.checked })}
                  disabled={restoringBackup.type === 'database'}
                >
                  Restore Files
                </Checkbox>
              </Space>
            </div>
          </Space>
        )}
      </Modal>
    </div>
  );
};

export default BackupsPage;
