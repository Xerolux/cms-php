import React, { useEffect, useState } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  Form,
  Input,
  Select,
  Switch,
  InputNumber,
  message,
  Popconfirm,
  Card,
  Row,
  Col,
  Tooltip,
} from 'antd';
import {
  PlusOutlined,
  EditOutlined,
  DeleteOutlined,
  EyeOutlined,
  MenuOutlined,
} from '@ant-design/icons';
import { pageService } from '../services/api';
import type { Page } from '../types';
import { Editor } from '@tinymce/tinymce-react';

const { TextArea } = Input;
const { Option } = Select;

const PagesPage: React.FC = () => {
  const [pages, setPages] = useState<Page[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [viewModalVisible, setViewModalVisible] = useState(false);
  const [editingPage, setEditingPage] = useState<Page | null>(null);
  const [viewingPage, setViewingPage] = useState<Page | null>(null);
  const [editorKey, setEditorKey] = useState(0);
  const [form] = Form.useForm();

  useEffect(() => {
    fetchPages();
  }, []);

  const fetchPages = async () => {
    setLoading(true);
    try {
      const data = await pageService.getAll();
      setPages(data.data || data);
    } catch (error) {
      message.error('Failed to fetch pages');
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setEditingPage(null);
    form.resetFields();
    form.setFieldsValue({
      template: 'default',
      is_visible: true,
      is_in_menu: true,
      menu_order: 0,
    });
    setEditorKey((prev) => prev + 1);
    setModalVisible(true);
  };

  const handleEdit = (page: Page) => {
    setEditingPage(page);
    form.setFieldsValue({
      ...page,
    });
    setEditorKey((prev) => prev + 1);
    setModalVisible(true);
  };

  const handleDelete = async (id: number) => {
    try {
      await pageService.delete(id);
      message.success('Page deleted successfully');
      fetchPages();
    } catch (error) {
      message.error('Failed to delete page');
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      const pageData = {
        ...values,
      };

      if (editingPage) {
        await pageService.update(editingPage.id, pageData);
        message.success('Page updated successfully');
      } else {
        await pageService.create(pageData);
        message.success('Page created successfully');
      }

      setModalVisible(false);
      form.resetFields();
      fetchPages();
    } catch (error) {
      message.error('Failed to save page');
    }
  };

  const handleView = (page: Page) => {
    setViewingPage(page);
    setViewModalVisible(true);
  };

  const getTemplateColor = (template: string) => {
    const colors: Record<string, string> = {
      default: 'blue',
      'full-width': 'green',
      landing: 'purple',
    };
    return colors[template] || 'default';
  };

  const columns = [
    {
      title: 'Order',
      dataIndex: 'menu_order',
      key: 'menu_order',
      width: 80,
      render: (order: number, record: Page) =>
        record.is_in_menu ? (
          <span style={{ fontWeight: 'bold' }}>{order}</span>
        ) : (
          <span style={{ color: '#999' }}>-</span>
        ),
      sorter: (a: Page, b: Page) => a.menu_order - b.menu_order,
    },
    {
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      sorter: (a: Page, b: Page) => a.title.localeCompare(b.title),
      render: (title: string, record: Page) => (
        <Space direction="vertical" size={0}>
          <span style={{ fontWeight: 500 }}>{title}</span>
          {record.slug && (
            <span style={{ fontSize: 12, color: '#999' }}>/pages/{record.slug}</span>
          )}
        </Space>
      ),
    },
    {
      title: 'Template',
      dataIndex: 'template',
      key: 'template',
      render: (template: string) => (
        <Tag color={getTemplateColor(template)}>
          {template === 'full-width' ? 'Full Width' : template.charAt(0).toUpperCase() + template.slice(1)}
        </Tag>
      ),
      filters: [
        { text: 'Default', value: 'default' },
        { text: 'Full Width', value: 'full-width' },
        { text: 'Landing', value: 'landing' },
      ],
      onFilter: (value: unknown, record: Page) => record.template === value,
    },
    {
      title: 'Status',
      key: 'status',
      render: (_: unknown, record: Page) => (
        <Space>
          <Tag color={record.is_visible ? 'success' : 'default'}>
            {record.is_visible ? 'Visible' : 'Hidden'}
          </Tag>
          {record.is_in_menu && (
            <Tag color="blue" icon={<MenuOutlined />}>
              In Menu
            </Tag>
          )}
        </Space>
      ),
      filters: [
        { text: 'Visible', value: 'visible' },
        { text: 'Hidden', value: 'hidden' },
        { text: 'In Menu', value: 'menu' },
      ],
      onFilter: (value: unknown, record: Page) => {
        if (value === 'visible') return record.is_visible;
        if (value === 'hidden') return !record.is_visible;
        if (value === 'menu') return record.is_in_menu;
        return true;
      },
    },
    {
      title: 'Created',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date: string) => new Date(date).toLocaleDateString(),
      sorter: (a: Page, b: Page) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    },
    {
      title: 'Updated',
      dataIndex: 'updated_at',
      key: 'updated_at',
      render: (date: string) => new Date(date).toLocaleDateString(),
      sorter: (a: Page, b: Page) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime(),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 150,
      render: (_: unknown, record: Page) => (
        <Space>
          <Tooltip title="View">
            <Button
              type="text"
              icon={<EyeOutlined />}
              onClick={() => handleView(record)}
            />
          </Tooltip>
          <Tooltip title="Edit">
            <Button
              type="text"
              icon={<EditOutlined />}
              onClick={() => handleEdit(record)}
            />
          </Tooltip>
          <Popconfirm
            title="Delete this page?"
            description="This action cannot be undone"
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
      <Card
        title="Static Pages Management"
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={handleCreate}>
            Create Page
          </Button>
        }
      >
        <Table
          columns={columns}
          dataSource={pages}
          rowKey="id"
          loading={loading}
          pagination={{
            pageSize: 10,
            showSizeChanger: true,
            showTotal: (total) => `Total ${total} pages`,
          }}
        />
      </Card>

      {/* Create/Edit Modal */}
      <Modal
        title={editingPage ? 'Edit Page' : 'Create Page'}
        open={modalVisible}
        onOk={handleSubmit}
        onCancel={() => {
          setModalVisible(false);
          form.resetFields();
        }}
        width={1000}
        okText="Save"
        style={{ top: 20 }}
        bodyStyle={{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }}
      >
        <Form form={form} layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                label="Title"
                name="title"
                rules={[{ required: true, message: 'Please enter page title' }]}
              >
                <Input placeholder="e.g., Impressum" />
              </Form.Item>
            </Col>

            <Col span={12}>
              <Form.Item
                label="Slug (optional)"
                name="slug"
                tooltip="Leave empty to auto-generate from title"
              >
                <Input placeholder="e.g., impressum" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            label="Content"
            name="content"
            rules={[{ required: true, message: 'Please enter page content' }]}
          >
            <Editor
              apiKey="no-api-key"
              key={editorKey}
              init={{
                height: 400,
                menubar: true,
                plugins: [
                  'advlist',
                  'autolink',
                  'lists',
                  'link',
                  'image',
                  'charmap',
                  'preview',
                  'anchor',
                  'searchreplace',
                  'visualblocks',
                  'code',
                  'fullscreen',
                  'insertdatetime',
                  'media',
                  'table',
                  'help',
                  'wordcount',
                ],
                toolbar:
                  'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link | code preview fullscreen help',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
              }}
            />
          </Form.Item>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                label="Template"
                name="template"
                rules={[{ required: true, message: 'Please select a template' }]}
              >
                <Select>
                  <Option value="default">Default (with sidebar)</Option>
                  <Option value="full-width">Full Width</Option>
                  <Option value="landing">Landing Page</Option>
                </Select>
              </Form.Item>
            </Col>

            <Col span={8}>
              <Form.Item
                label="Menu Order"
                name="menu_order"
                rules={[{ required: true, message: 'Please enter menu order' }]}
                tooltip="Lower numbers appear first"
              >
                <InputNumber min={0} max={999} style={{ width: '100%' }} />
              </Form.Item>
            </Col>

            <Col span={8}>
              <Form.Item
                label="Visibility"
                name="is_visible"
                valuePropName="checked"
              >
                <Switch checkedChildren="Visible" unCheckedChildren="Hidden" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                label="Show in Menu"
                name="is_in_menu"
                valuePropName="checked"
              >
                <Switch checkedChildren="Yes" unCheckedChildren="No" />
              </Form.Item>
            </Col>

            <Col span={12}>
              {form.getFieldValue('is_in_menu') && (
                <Form.Item
                  label="Menu Position"
                  tooltip="Order in which this page appears in navigation"
                >
                  <InputNumber
                    value={form.getFieldValue('menu_order')}
                    onChange={(value) => form.setFieldValue('menu_order', value)}
                    min={0}
                    max={999}
                    style={{ width: '100%' }}
                  />
                </Form.Item>
              )}
            </Col>
          </Row>

          <Card title="SEO Settings" size="small" style={{ marginTop: 16 }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  label="Meta Title"
                  name="meta_title"
                  tooltip="Optional. Leave empty to use page title"
                >
                  <Input
                    placeholder="SEO title (max 60 chars recommended)"
                    maxLength={60}
                  />
                </Form.Item>
              </Col>

              <Col span={12}>
                <Form.Item
                  label="Meta Description"
                  name="meta_description"
                  tooltip="Optional. Short description for search engines"
                >
                  <TextArea
                    rows={2}
                    placeholder="SEO description (max 160 chars recommended)"
                    maxLength={160}
                  />
                </Form.Item>
              </Col>
            </Row>
          </Card>
        </Form>
      </Modal>

      {/* View Modal */}
      <Modal
        title={viewingPage?.title}
        open={viewModalVisible}
        onCancel={() => setViewModalVisible(false)}
        footer={null}
        width={800}
      >
        {viewingPage && (
          <div>
            <Space style={{ marginBottom: 16 }}>
              <Tag color={getTemplateColor(viewingPage.template)}>
                {viewingPage.template === 'full-width'
                  ? 'Full Width'
                  : viewingPage.template.charAt(0).toUpperCase() +
                    viewingPage.template.slice(1)}
              </Tag>
              <Tag color={viewingPage.is_visible ? 'success' : 'default'}>
                {viewingPage.is_visible ? 'Visible' : 'Hidden'}
              </Tag>
              {viewingPage.is_in_menu && (
                <Tag color="blue" icon={<MenuOutlined />}>
                  In Menu (Order: {viewingPage.menu_order})
                </Tag>
              )}
            </Space>

            <Card size="small" style={{ marginBottom: 16 }}>
              <Row gutter={16}>
                <Col span={8}>
                  <div style={{ fontSize: 12, color: '#999' }}>Slug</div>
                  <div style={{ fontFamily: 'monospace' }}>/{viewingPage.slug}</div>
                </Col>
                <Col span={8}>
                  <div style={{ fontSize: 12, color: '#999' }}>Created</div>
                  <div>{new Date(viewingPage.created_at).toLocaleString()}</div>
                </Col>
                <Col span={8}>
                  <div style={{ fontSize: 12, color: '#999' }}>Updated</div>
                  <div>{new Date(viewingPage.updated_at).toLocaleString()}</div>
                </Col>
              </Row>
            </Card>

            {viewingPage.meta_title && (
              <Card size="small" style={{ marginBottom: 16 }}>
                <div style={{ fontSize: 12, color: '#999', marginBottom: 4 }}>
                  SEO Meta Title
                </div>
                <div>{viewingPage.meta_title}</div>
              </Card>
            )}

            {viewingPage.meta_description && (
              <Card size="small" style={{ marginBottom: 16 }}>
                <div style={{ fontSize: 12, color: '#999', marginBottom: 4 }}>
                  SEO Meta Description
                </div>
                <div>{viewingPage.meta_description}</div>
              </Card>
            )}

            <Card size="small" title="Content Preview">
              <div
                dangerouslySetInnerHTML={{ __html: viewingPage.content }}
                style={{
                  maxHeight: 400,
                  overflowY: 'auto',
                  padding: 16,
                  border: '1px solid #f0f0f0',
                  borderRadius: 4,
                }}
              />
            </Card>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default PagesPage;
